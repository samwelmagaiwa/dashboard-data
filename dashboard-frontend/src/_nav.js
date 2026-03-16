import { h } from 'vue'

export default [
  {
    component: 'CNavItem',
    name: 'Dashboard',
    to: '/dashboard',
    icon: () =>
      h('img', {
        src: new URL('@/assets/icons/dashboard.png', import.meta.url).href,
        alt: 'Dashboard',
        class: 'nav-icon',
        style: 'width: 1.5rem; height: 1.5rem;',
      }),
    // badge: {
    //   color: 'primary',
    //   text: 'NEW'
    // }
  },
  {
    component: 'CNavItem',
    name: 'Reports',
    to: '/reports',
    icon: 'cil-notes',
  },
  {
    component: 'CNavTitle',
    name: 'Pages',
  },
  {
    component: 'CNavItem',
    name: 'Logout',
    to: '/logout',
    icon: 'cil-account-logout',
  },
]
