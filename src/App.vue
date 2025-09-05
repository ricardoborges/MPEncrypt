<template>
  <NcAppContent>
    <div class="wrapper">
      <h2>{{ t('mpencrypt', 'Cadastro de Chaves Públicas dos MPs Destinatários') }}</h2>

      <form class="form" @submit.prevent="onSave">
        <NcTextField v-model="form.name" :label="t('mpencrypt', 'Destinatário')" required />
        <NcTextArea v-model="form.publicKey" :label="t('mpencrypt', 'Chave pública (ASCII-Armored)')" required :minRows="6" />
        <NcButton type="submit" :disabled="saving">
          {{ saving ? t('mpencrypt', 'Salvando...') : t('mpencrypt', 'Salvar') }}
        </NcButton>
      </form>

      <div class="list">
        <h3>{{ t('mpencrypt', 'Cadastrados') }}</h3>
        <div v-if="loading" class="loading">{{ t('mpencrypt', 'Carregando...') }}</div>
        <div v-else-if="items.length === 0" class="empty">{{ t('mpencrypt', 'Nenhum destinatário cadastrado') }}</div>
        <ul v-else class="items">
          <li v-for="r in items" :key="r.id" class="item">
            <div class="meta">
              <div class="name">{{ r.name }}</div>
              <div class="fingerprint">{{ keyPreview(r.publicKey) }}</div>
            </div>
            <NcButton type="button" @click="onDelete(r)" :disabled="deletingId === r.id">
              {{ deletingId === r.id ? t('mpencrypt', 'Excluindo...') : t('mpencrypt', 'Excluir') }}
            </NcButton>
          </li>
        </ul>
      </div>

      <hr />

      <div class="private">
        <h2>{{ t('mpencrypt', 'Chave Privada do Usuário') }}</h2>
        <div class="status">
          <strong>{{ t('mpencrypt', 'Status') }}:</strong>
          <span>{{ private.exists ? t('mpencrypt', 'Cadastrada') : t('mpencrypt', 'Não cadastrada') }}</span>
        </div>
        <form class="form" @submit.prevent="onSavePrivate">
          <NcTextArea v-model="privateForm.value" :label="t('mpencrypt', 'Chave privada (ASCII-Armored)')" :minRows="6" :placeholder="t('mpencrypt', 'Cole sua chave privada aqui')" />
          <div class="actions">
            <NcButton type="submit" :disabled="savingPrivate || !privateForm.value">
              {{ savingPrivate ? t('mpencrypt', 'Salvando...') : t('mpencrypt', private.exists ? 'Atualizar' : 'Cadastrar') }}
            </NcButton>
            <NcButton v-if="private.exists" type="button" :disabled="deletingPrivate" @click="onDeletePrivate">
              {{ deletingPrivate ? t('mpencrypt', 'Excluindo...') : t('mpencrypt', 'Excluir') }}
            </NcButton>
          </div>
          <div class="hint">{{ t('mpencrypt', 'Após salvar, o conteúdo não será exibido novamente nesta tela.') }}</div>
        </form>
      </div>
    </div>
  </NcAppContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcTextArea from '@nextcloud/vue/dist/Components/NcTextArea.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import { listRecipients, createRecipient, deleteRecipient, getPrivateKeyMeta, setPrivateKey, deletePrivateKey } from './api'

export default {
  name: 'App',
  components: { NcAppContent, NcTextField, NcTextArea, NcButton },
  data: () => ({
    items: [],
    loading: true,
    saving: false,
    deletingId: null,
    form: { name: '', publicKey: '' },
    private: { exists: false },
    privateForm: { value: '' },
    savingPrivate: false,
    deletingPrivate: false,
  }),
  async mounted() {
    await Promise.all([this.refresh(), this.refreshPrivate()])
  },
  methods: {
    async refresh() {
      this.loading = true
      try {
        this.items = await listRecipients()
      } finally {
        this.loading = false
      }
    },
    async refreshPrivate() {
      this.private = await getPrivateKeyMeta()
    },
    async onSave() {
      if (!this.form.name || !this.form.publicKey) return
      this.saving = true
      try {
        await createRecipient({ name: this.form.name, publicKey: this.form.publicKey })
        this.form.name = ''
        this.form.publicKey = ''
        await this.refresh()
      } finally {
        this.saving = false
      }
    },
    async onDelete(r) {
      this.deletingId = r.id
      try {
        await deleteRecipient(r.id)
        await this.refresh()
      } finally {
        this.deletingId = null
      }
    },
    async onSavePrivate() {
      if (!this.privateForm.value) return
      this.savingPrivate = true
      try {
        await setPrivateKey(this.privateForm.value)
        this.privateForm.value = ''
        await this.refreshPrivate()
      } finally {
        this.savingPrivate = false
      }
    },
    async onDeletePrivate() {
      this.deletingPrivate = true
      try {
        await deletePrivateKey()
        await this.refreshPrivate()
      } finally {
        this.deletingPrivate = false
      }
    },
    keyPreview(pub) {
      const s = String(pub || '').trim().split('\n').filter(Boolean)
      const head = s[0] || ''
      const tail = s[s.length - 1] || ''
      return `${head}${head && tail ? ' … ' : ''}${tail}`
    },
  },
}
</script>

<style scoped lang="scss">
.wrapper { padding: 16px; max-width: 900px; }
.form { display: grid; gap: 12px; margin-bottom: 20px; }
.list .items { list-style: none; padding: 0; margin: 0; }
.item { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--color-border); }
.item .name { font-weight: 600; }
.item .fingerprint { color: var(--color-text-maxcontrast); font-family: monospace; font-size: 12px; }
.loading, .empty { color: var(--color-text-maxcontrast); }
.private { margin-top: 24px; }
.private .actions { display: flex; gap: 8px; align-items: center; }
.status { margin-bottom: 8px; }
</style>
