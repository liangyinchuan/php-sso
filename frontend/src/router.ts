import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from './stores/auth'
import Login from './views/Login.vue'
import Consent from './views/Consent.vue'
import ErrorPage from './views/ErrorPage.vue'
import Account from './views/Account.vue'
import DemoStart from './views/demo/Start.vue'
import DemoCallback from './views/demo/Callback.vue'
import AdminLayout from './layouts/AdminLayout.vue'
import Dashboard from './views/admin/Dashboard.vue'
import Apps from './views/admin/Apps.vue'
import AppEdit from './views/admin/AppEdit.vue'
import Users from './views/admin/Users.vue'
import UserEdit from './views/admin/UserEdit.vue'
import Logs from './views/admin/Logs.vue'
import Requests from './views/admin/Requests.vue'
import Guide from './views/admin/Guide.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', component: Login },
    { path: '/consent', component: Consent, meta: { auth: true } },
    { path: '/error', component: ErrorPage },
    { path: '/account', component: Account, meta: { auth: true } },
    { path: '/demo', component: DemoStart },
    { path: '/demo/callback', component: DemoCallback },
    {
      path: '/admin',
      component: AdminLayout,
      meta: { admin: true },
      children: [
        { path: '', component: Dashboard },
        { path: 'apps', component: Apps },
        { path: 'apps/new', component: AppEdit },
        { path: 'apps/:id', component: AppEdit },
        { path: 'users', component: Users },
        { path: 'users/new', component: UserEdit },
        { path: 'users/:id', component: UserEdit },
        { path: 'logs', component: Logs },
        { path: 'requests', component: Requests },
        { path: 'guide', component: Guide },
      ],
    },
    { path: '/', redirect: '/account' },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuth()
  if (!auth.loaded) await auth.load()
  if ((to.meta.auth || to.meta.admin) && !auth.user) {
    const query: Record<string, string> = { redirect: to.fullPath }
    if (typeof to.query.request_id === 'string') query.request_id = to.query.request_id
    return { path: '/login', query }
  }
  if (to.meta.admin && auth.user && !auth.user.is_admin) return '/account'
  if (to.path === '/login' && auth.user && !to.query.request_id) {
    return auth.user.is_admin ? '/admin' : '/account'
  }
})

export default router
