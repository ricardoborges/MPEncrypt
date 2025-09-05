<template>
  <NcDialog :show.sync="show" :name="t('mpencrypt', 'Criptografar arquivo')" size="normal" @close="close">
    <div class="dialog-body">
      <p class="file-row">
        <strong>{{ t('mpencrypt', 'Arquivo') }}:</strong>
        <span class="file-name">{{ file?.name || '-' }}</span>
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
import { listRecipients } from '../api'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'

export default {
  name: 'EncryptDialog',
  components: { NcDialog, NcButton, NcSelect },
  props: {
    file: { type: Object, required: true },
    onClose: { type: Function, required: true },
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
        const name = this.selected.label
        showSuccess(this.t('mpencrypt', 'Preparando criptografia para {recipient}', { recipient: name }))
        // Placeholder: aqui futuramente chamaremos o backend para gerar a criptografia
      } finally {
        this.generating = false
        this.close()
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

