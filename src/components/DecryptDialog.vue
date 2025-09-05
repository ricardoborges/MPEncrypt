<template>
  <NcDialog :show.sync="show" :name="t('mpencrypt', 'Descriptografar arquivo')" size="normal" @close="close">
    <div class="dialog-body">
      <p class="file-row">
        <strong>{{ t('mpencrypt', 'Arquivo') }}:</strong>
        <span class="file-name">{{ fileName }}</span>
      </p>

      <div v-if="loading" class="hint">{{ t('mpencrypt', 'Verificando chave privada...') }}</div>
      <div v-else-if="!hasPrivateKey" class="hint">
        {{ t('mpencrypt', 'Nenhuma chave privada cadastrada. Abra o app MPEncrypt para cadastrar sua chave privada.') }}
      </div>
      <div v-else class="hint">
        {{ t('mpencrypt', 'Chave privada cadastrada.') }}
      </div>
    </div>

    <template #actions>
      <NcButton type="button" @click="close">{{ t('mpencrypt', 'Cancelar') }}</NcButton>
      <NcButton type="primary" :disabled="!canGenerate || generating" @click="generate">
        {{ generating ? t('mpencrypt', 'Gerando...') : t('mpencrypt', 'Gerar') }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script>
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import { getPrivateKeyMeta, getPrivateKeyValue } from '../api'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import * as openpgp from 'openpgp'
import { getClient as davGetClient, getRootPath as davGetRootPath, getRemoteURL as davGetRemoteURL } from '@nextcloud/files/dav'

export default {
  name: 'DecryptDialog',
  components: { NcDialog, NcButton },
  props: {
    file: { type: Object, required: true },
    onClose: { type: Function, required: true },
    onCreated: { type: Function, required: false },
  },
  data: () => ({
    show: true,
    loading: true,
    generating: false,
    // no form fields needed
    hasPrivateKey: false,
  }),
  async mounted() {
    try {
      const meta = await getPrivateKeyMeta()
      this.hasPrivateKey = !!meta.exists
    } catch (e) {
      console.error('[mpencrypt] Failed to read private key meta', e)
      this.hasPrivateKey = false
    } finally {
      this.loading = false
    }
  },
  computed: {
    fileName() {
      const f = this.file || {}
      return (
        f.name || f.basename || f.filename || (typeof f.path === 'string' && f.path.split('/').filter(Boolean).pop()) || '-'
      )
    },
    canGenerate() { return this.hasPrivateKey === true },
  },
  methods: {
    close() {
      this.show = false
      this.onClose && this.onClose()
    },
    computeRelPath(node) {
      const dir = (node.directory || '').replace(/^\/+/, '')
      const fname = node.name || node.basename || node.filename || ''
      const pathFromDir = dir && fname ? `${dir.replace(/\/+$/, '')}/${fname}` : ''
      const rawPath = node.path || pathFromDir || fname
      return (typeof rawPath === 'string' ? rawPath.replace(/^\/+/, '') : '')
    },
    async generate() {
      // no-op; key is fetched from server for current user
      this.generating = true
      try {
        const node = this.file || {}
        const relPath = this.computeRelPath(node)
        const armoredPrivateKey = await getPrivateKeyValue()
        if (!armoredPrivateKey) {
          throw new Error(this.t('mpencrypt', 'Nenhuma chave privada cadastrada'))
        }
        const outName = await this.decryptClientSide(armoredPrivateKey, '', relPath)
        showSuccess(this.t('mpencrypt', 'Arquivo descriptografado gerado: {name}', { name: outName }))
        this.onCreated && this.onCreated({ name: outName })
        this.close()
      } catch (e) {
        console.error('[mpencrypt] Decryption failed', e)
        const msg = e?.message || 'Falha ao descriptografar'
        showError(this.t('mpencrypt', msg))
      } finally {
        this.generating = false
      }
    },
    async decryptClientSide(armoredPrivateKey, passphrase, relPath) {
      const remote = davGetRemoteURL ? davGetRemoteURL() : undefined
      const client = davGetClient(remote)
      const root = davGetRootPath ? davGetRootPath() : '/files'
      const fullPath = `${root}/${relPath}`.replace(/\/+/, '/')

      // Download encrypted file
      const ab = await client.getFileContents(fullPath, { format: 'binary' })
      const data = ab instanceof ArrayBuffer ? new Uint8Array(ab) : new Uint8Array(ab?.data || ab || [])

      // Prepare keys
      const priv = await openpgp.readPrivateKey({ armoredKey: armoredPrivateKey })
      const decPriv = passphrase ? await openpgp.decryptKey({ privateKey: priv, passphrase }) : priv

      // Read message and decrypt
      const message = await openpgp.readMessage({ binaryMessage: data })
      const decrypted = await openpgp.decrypt({ message, decryptionKeys: decPriv, format: 'binary' })
      const plain = decrypted && decrypted.data ? decrypted.data : null
      if (!(plain instanceof Uint8Array)) {
        throw new Error(this.t('mpencrypt', 'Conteúdo descriptografado inválido'))
      }
      const outAb = plain.buffer.slice(plain.byteOffset, plain.byteOffset + plain.byteLength)

      // Destination file name: remove .pgp/.gpg/.asc
      const destPath = await this.pickUniqueDestPath(client, this.deriveDecryptedPath(fullPath))
      await client.putFileContents(destPath, outAb, {
        overwrite: false,
        contentType: 'application/octet-stream',
        contentLength: outAb.byteLength,
      })

      return destPath.split('/').pop()
    },
    deriveDecryptedPath(fullPath) {
      const suffixes = ['.pgp', '.gpg', '.asc']
      for (const s of suffixes) {
        if (fullPath.toLowerCase().endsWith(s)) {
          return fullPath.slice(0, -s.length)
        }
      }
      return fullPath + '.decrypted'
    },
    async pickUniqueDestPath(client, fullDest) {
      const parts = fullDest.split('/')
      const name = parts.pop()
      const dir = parts.join('/')
      const dot = name.lastIndexOf('.')
      const base = dot > 0 ? name.slice(0, dot) : name
      const ext = dot > 0 ? name.slice(dot) : ''
      let n = 0
      while (true) {
        const candidate = `${dir}/${n === 0 ? base : base + ' (' + n + ')'}${ext}`
        try { await client.stat(candidate); n++ } catch { return candidate }
      }
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
