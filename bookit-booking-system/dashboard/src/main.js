import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import routes from './router'
import './assets/main.css'

// Create router with base path.
const router = createRouter({
  history: createWebHistory('/bookit-dashboard/app/'),
  routes
})

// Create and mount app.
const app = createApp(App)
app.use(router)
app.mount('#app')
