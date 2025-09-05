import Vue from 'vue'
import { registerFileAction } from '@nextcloud/files'
import { showError } from '@nextcloud/dialogs'
import EncryptDialog from './components/EncryptDialog.vue'

console.log('[mpencrypt] Registering Files action...')

// Helpers to work with Files API signatures: (nodes: Array, view)
const getSingleNode = (nodes) => Array.isArray(nodes) ? nodes[0] : nodes
const isDirectoryMime = (m) => typeof m === 'string' && (m === 'httpd/unix-directory' || m.endsWith('/directory'))
const isFileNode = (node) => {
    if (!node || typeof node !== 'object') return false
    if (node.type && String(node.type).toLowerCase() === 'dir') return false
    if (node.isdir === true || node.isDirectory === true) return false
    if (isDirectoryMime(node.mimetype)) return false
    // Require a name and either fileid or size (directories usually size 0 but not reliable)
    if (!node.name && !node.basename) return false
    return true
}

// Register a "Criptografar" action in the Files app context menu.
try {
	const icon = () => '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 2a5 5 0 00-5 5v3H6a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2v-8a2 2 0 00-2-2h-1V7a5 5 0 00-5-5zm-3 8V7a3 3 0 116 0v3H9zm3 4a2 2 0 100 4 2 2 0 000-4z"/></svg>'
	const action = {
		id: 'mpencrypt-encrypt',
		displayName: () => 'Criptografar',
		// Files calls with: (nodes, view)
		iconSvgInline: () => icon(),
		order: 50,
		// Be permissive so the action shows up reliably
    enabled: (nodes /*, view */) => {
        const node = getSingleNode(nodes)
        return isFileNode(node)
    },
		// Exec receives (nodes, view)
		exec: async (nodes, view) => {
        const node = getSingleNode(nodes)
        console.log('[mpencrypt] Encrypt action clicked for:', node)
        if (!isFileNode(node)) {
            showError('Selecione um arquivo (não uma pasta)')
            return
        }
			// Mount modal dialog programmatically
			const mount = document.createElement('div')
			document.body.appendChild(mount)
			let vm
			const destroy = () => {
				if (vm) {
					vm.$destroy()
				}
				mount.remove()
			}
			vm = new Vue({
				render: h => h(EncryptDialog, { props: { file: node, onClose: destroy, onCreated: (_info) => {
            let triggered = false
            try { if (view && typeof view.reload === 'function') { view.reload(); triggered = true } } catch {}
            try { if (view && view.fileList && typeof view.fileList.reload === 'function') { view.fileList.reload(); triggered = true } } catch {}
            try { if (window && window.OCA && window.OCA.Files && window.OCA.Files.App && window.OCA.Files.App.fileList && typeof window.OCA.Files.App.fileList.reload === 'function') { window.OCA.Files.App.fileList.reload(); triggered = true } } catch {}
            // As a pragmatic fallback, force a page refresh if nothing above could be called
            if (!triggered) {
              setTimeout(() => { try { window.location && window.location.reload && window.location.reload() } catch {} }, 600)
            }
          } } }),
			})
			vm.$mount(mount)
		},
	}

	registerFileAction(action)
	console.log('[mpencrypt] Files action registered successfully')
} catch (error) {
	console.error('[mpencrypt] Error registering Files action:', error)
}
// Provide l10n helpers for this entrypoint (separate from main.js)
Vue.mixin({ methods: { t: window.t, n: window.n } })
