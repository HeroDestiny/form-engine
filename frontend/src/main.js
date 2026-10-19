import { createApp } from 'vue'
import './style.css'
import RootApp from './RootApp.vue'
import router from './router'
import { syncAuthToken } from './auth/token.js'

const app = createApp(RootApp)

app.use(router)
window.addEventListener('storage', syncAuthToken)
app.mount('#app')
