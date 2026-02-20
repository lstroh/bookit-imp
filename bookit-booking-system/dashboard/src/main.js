import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import NProgress from 'nprogress'
import 'nprogress/nprogress.css'
import App from './App.vue'
import routes from './router'
import './assets/main.css'

NProgress.configure({ showSpinner: false })

// Create router with base path.
const router = createRouter({
  history: createWebHistory('/bookit-dashboard/app/'),
  routes
})

router.beforeEach((to, from, next) => {
  NProgress.start()
  next()
})

router.afterEach(() => {
  NProgress.done()
})

// Create and mount app.
const app = createApp(App)
app.use(router)
app.mount('#app')
