<script setup>
import { CContainer } from '@coreui/vue'
import AppFooter from '@/components/AppFooter.vue'
import PublicHeader from '@/components/PublicHeader.vue'
</script>

<template>
  <div>
    <div class="wrapper d-flex flex-column min-vh-100">
      <PublicHeader />
      <div class="body flex-grow-1">
        <CContainer class="px-4" fluid>
          <router-view />
        </CContainer>
      </div>
      <AppFooter />
    </div>
  </div>
</template>

<style scoped>
.wrapper {
  padding-left: 0 !important; /* No sidebar space needed */
}
</style>
