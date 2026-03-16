import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import CoreuiVue from '@coreui/vue'
import CIcon from '@coreui/icons-vue'
import {iconsSet as icons } from '@/assets/icons'
import CoreuiIcons from '@coreui/icons-vue'
// import DocsComponents from '@/components/MNHComponents'
// import DocsExample from '@/components/MNHExample'
//import DocsIcons from '@/components/MNHIcons'

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(CoreuiVue)
app.provide('icons', icons)
app.component('CIcon', CIcon)
app.mount('#app')
app.use(CoreuiIcons)
