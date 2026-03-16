import { h, resolveComponent } from 'vue'
import { createRouter, createWebHashHistory } from 'vue-router'
import PublicLayout from '@/layouts/PublicLayout'
import DefaultLayout from '@/layouts/DefaultLayout'

const routes = [
  {
    path: '/',
    name: 'PublicHome',
    component: PublicLayout,
    redirect: '/public-dashboard',
    children: [
      {
        path: '/public-dashboard',
        name: 'PublicDashboard',
        component: () => import('@/views/dashboard/Dashboard.vue'),
      },
      {
        path: '/reports',
        name: 'Reports',
        component: () => import('@/views/reports/Reports.vue'),
      },
    ],
  },
  {
    path: '/admin',
    name: 'Home',
    component: DefaultLayout,
    redirect: '/dashboard',
    children: [
      {
        path: '/dashboard',
        name: 'Dashboard',
        alias: '/admin-dashboard',
        component: () => import('@/views/dashboard/AdminDashboard.vue'),
      },
      {
        path: '/charts',
        name: 'Charts',
        component: () => import('@/views/charts/Charts.vue'),
      },
    ],
  },
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/pages/Login.vue'),
  },
  {
    path: '/profile',
    name: 'Profile',
    component: () => import('@/views/pages/Profile.vue'),
  },
  {
    path: '/logout',
    name: 'Logout',
    beforeEnter: (to, from, next) => {
      // Clear auth data
      localStorage.removeItem('mnh_token')
      localStorage.removeItem('mnh_user')
      // Redirect to login
      next({ name: 'Login' })
    },
  },
]

const router = createRouter({
  history: createWebHashHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior() {
    // always scroll to top
    return { top: 0 }
  },
})

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('mnh_token')
  const user = JSON.parse(localStorage.getItem('mnh_user') || 'null')
  const publicRoutes = ['Login', 'PublicDashboard', 'Reports']
  const adminRoles = ['ED', 'DED', 'DICT']

  // Check if route requires authentication
  const authRequired = !publicRoutes.includes(to.name)

  if (authRequired && !token) {
    return next({ name: 'Login' })
  }

  // Protect admin dashboard
  if (to.name === 'Dashboard') {
    if (!token || !user || !adminRoles.includes(user.role)) {
      return next({ name: 'PublicDashboard' })
    }
  }

  // Redirect authenticated users away from login
  if (to.name === 'Login' && token) {
    if (user && adminRoles.includes(user.role)) {
      return next({ name: 'Dashboard' })
    }
    return next({ name: 'PublicDashboard' })
  }

  next()
})

export default router
