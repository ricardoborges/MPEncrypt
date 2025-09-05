<template>
  <NcDialog :show.sync="show" :name="t('mpencrypt', 'Criptografar arquivo')" size="normal" @close="close">
    <div class="dialog-body">
      <p class="file-row">
        <strong>{{ t('mpencrypt', 'Arquivo') }}:</strong>
        <span class="file-name">{{ fileName }}</span>
      </p>

      <div class="field">
        <label>{{ t('mpencrypt', 'Destinatário') }}</label>
        <div v-if="loading" class="hint">{{ t('mpencrypt', 'Carregando destinatários...') }}</div>
        <NcSelect v-else v-model="selected" :options="options" :placeholder="t('mpencrypt', 'Selecione um destinatário')" :clearable="false" />
        <div v-if="!loading && options.length === 0" class="hint">
          {{ t('mpencrypt', 'Nenhum destinatário cadastrado. Abra o app para adicionar.') }}
        </div>
      </div>
    </div>

    <template #actions>
      <NcButton type="button" @click="close">{{ t('mpencrypt', 'Cancelar') }}</NcButton>
      <NcButton type="primary" :disabled="!selected || generating" @click="generate">
        {{ generating ? t('mpencrypt', 'Gerando...') : t('mpencrypt', 'Gerar') }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script>
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import { listRecipients, encryptFile } from '../api'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import * as openpgp from 'openpgp'
import { getClient as davGetClient, getRootPath as davGetRootPath, getRemoteURL as davGetRemoteURL } from '@nextcloud/files/dav'

export default {
  name: 'EncryptDialog',
  components: { NcDialog, NcButton, NcSelect },
  props: {
    file: { type: Object, required: true },
    onClose: { type: Function, required: true },
    onCreated: { type: Function, required: false },
  },
  data: () => ({
    show: true,
    loading: true,
    generating: false,
    options: [],
    selected: null,
  }),
  async mounted() {
    try {
      const items = await listRecipients()
      this.options = items.map(r => ({ label: r.name, value: r }))
      if (this.options.length > 0) this.selected = this.options[0]
    } catch (e) {
      console.error('[mpencrypt] Failed to load recipients', e)
      showError(this.t('mpencrypt', 'Falha ao carregar destinatários'))
    } finally {
      this.loading = false
    }
  },
  methods: {
    close() {
      this.show = false
      this.onClose && this.onClose()
    },
    async generate() {
      if (!this.selected) {
        showWarning(this.t('mpencrypt', 'Selecione um destinatário'))
        return
      }
      this.generating = true
      try {
        const node = this.file || {}
        const recipient = this.selected.value
        const relPath = this.computeRelPath(node)
        const outName = await this.encryptClientSide(recipient.publicKey, relPath)
        showSuccess(this.t('mpencrypt', 'Arquivo criptografado gerado: {name}', { name: outName }))
        this.onCreated && this.onCreated({ name: outName })
        this.close()
      } catch (e) {
        console.error('[mpencrypt] Encryption failed', e)
        // Fallback to server-side API if client-side fails unexpectedly
        try {
          const node = this.file || {}
          const relPath = this.computeRelPath(node)
          const payload = {
            recipientId: this.selected.value.id,
            fileId: node.id || node.fileid || 0,
            filePath: relPath,
          }
          const res = await encryptFile(payload)
          const outName = res?.name || 'arquivo.pgp'
          showSuccess(this.t('mpencrypt', 'Arquivo criptografado gerado: {name}', { name: outName }))
          this.onCreated && this.onCreated({ name: outName })
          this.close()
          return
        } catch (serverErr) {
          console.error('[mpencrypt] Server-side fallback failed', serverErr)
        }
        const msg = e?.response?.data?.ocs?.data?.message || e?.response?.data?.message || e?.message || 'Falha ao criptografar'
        showError(this.t('mpencrypt', msg))
      } finally {
        this.generating = false
      }
    },
    computeRelPath(node) {
      const dir = (node.directory || '').replace(/^\/+/, '')
      const fname = node.name || node.basename || node.filename || ''
      const pathFromDir = dir && fname ? `${dir.replace(/\/+$/, '')}/${fname}` : ''
      const rawPath = node.path || pathFromDir || fname
      return (typeof rawPath === 'string' ? rawPath.replace(/^\/+/, '') : '')
    },
    async encryptClientSide(armoredPublicKey, relPath) {
      // Create DAV client
      const remote = davGetRemoteURL ? davGetRemoteURL() : undefined
      const client = davGetClient(remote)
      const root = davGetRootPath ? davGetRootPath() : '/files'
      const fullPath = `${root}/${relPath}`.replace(/\/+/, '/')

      // Download file as binary
      const ab = await client.getFileContents(fullPath, { format: 'binary' })
      const data = ab instanceof ArrayBuffer ? new Uint8Array(ab) : new Uint8Array(ab?.data || ab || [])

      // Prepare OpenPGP keys and message
      const pub = await openpgp.readKey({ armoredKey: armoredPublicKey })
      const message = await openpgp.createMessage({ binary: data })

      // Encrypt as binary (.pgp)
      const encrypted = await openpgp.encrypt({ message, encryptionKeys: pub, format: 'binary' })
      const cipher = encrypted // Uint8Array
      // Convert to a tight ArrayBuffer (slice to avoid using the whole underlying buffer)
      const outAb = cipher.buffer.slice(cipher.byteOffset, cipher.byteOffset + cipher.byteLength)

      // Build destination unique path
      const destPath = await this.pickUniqueDestPath(client, fullPath + '.pgp')
      await client.putFileContents(destPath, outAb, {
        overwrite: false,
        contentType: 'application/pgp-encrypted',
        contentLength: outAb.byteLength,
      })

      return destPath.split('/').pop()
    },
    async pickUniqueDestPath(client, fullDest) {
      const parts = fullDest.split('/')
      const name = parts.pop()
      const dir = parts.join('/')
      const dot = name.lastIndexOf('.')
      const base = dot > 0 ? name.slice(0, dot) : name
      const ext = dot > 0 ? name.slice(dot) : ''
      let n = 0
      // Try base, base (1), base (2), ...
      while (true) {
        const candidate = `${dir}/${n === 0 ? base : base + ' (' + n + ')'}${ext}`
        try {
          await client.stat(candidate)
          n++
        } catch (err) {
          // Assume not found -> use candidate
          return candidate
        }
      }
    },
  },
  computed: {
    fileName() {
      const f = this.file || {}
      // Prefer common attributes, fall back to last path segment
      return (
        f.name ||
        f.basename ||
        f.filename ||
        (typeof f.path === 'string' && f.path.split('/').filter(Boolean).pop()) ||
        '-'
      )
    },
  },
}
</script>

<style scoped>
.dialog-body { display: grid; gap: 12px; }
.file-row { margin: 0; }
.file-name { margin-left: 6px; }
.hint { color: var(--color-text-maxcontrast); font-size: 12px; }
.field label { display: block; margin-bottom: 6px; font-weight: 600; }
</style>

