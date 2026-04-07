import { createApp } from 'vue'
import './style.css'
import RootApp from './RootApp.vue'
import router from './router'

const app = createApp(RootApp)

app.use(router)
app.mount('#app')
