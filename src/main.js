import Vue from 'vue'
import App from './App.vue'

// Use global Nextcloud l10n helpers without requiring extra deps
Vue.mixin({ methods: { t: window.t, n: window.n } })

const View = Vue.extend(App)
new View().$mount('#mpencrypt')
