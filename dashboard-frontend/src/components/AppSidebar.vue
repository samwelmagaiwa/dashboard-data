<script setup>
import { RouterLink } from 'vue-router'
import { AppSidebarNav } from '@/components/AppSidebarNav.js'
import { useSidebarStore } from '@/stores/sidebar.js'

const sidebar = useSidebarStore()
</script>

<template>
  <CSidebar
    class="sidebar-custom border-end"
    position="fixed"
    :unfoldable="sidebar.unfoldable"
    :visible="sidebar.visible"
    :narrow="sidebar.narrow"
    @visible-change="(value) => sidebar.toggleVisible(value)"
  >
    <CSidebarHeader class="border-bottom">
      <RouterLink custom to="/" v-slot="{ href, navigate }">
        <CSidebarBrand v-bind="$attrs" as="a" :href="href" @click="navigate">
          <img
            src="@/assets/images/muhilogo.jpg"
            class="sidebar-brand-full flip-logo"
            height="60"
            width="100"
          />
          <img
            src="@/assets/images/muhilogo.jpg"
            class="sidebar-brand-narrow flip-logo"
            height="60"
            width="100"
          />
        </CSidebarBrand>
      </RouterLink>
      <CCloseButton class="d-lg-none" dark @click="sidebar.toggleVisible()" />
    </CSidebarHeader>
    <AppSidebarNav />
    <CSidebarFooter class="border-top d-none d-lg-flex">
      <CSidebarToggler @click="sidebar.toggleNarrow()" />
    </CSidebarFooter>
  </CSidebar>
</template>

<style scoped>
.flip-logo {
  animation: flip 2s infinite linear;
}

@keyframes flip {
  0% {
    transform: rotateY(0deg);
  }
  50% {
    transform: rotateY(180deg);
  }
  100% {
    transform: rotateY(360deg);
  }
}

.sidebar-custom {
  --cui-sidebar-narrow-width: 104px;
  background: linear-gradient(180deg, #007f3e 0%, #003082 100%);
  color: #ffffff;
  transition: width 0.3s ease-in-out;
  border-right: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
  border-radius: 0 10px 10px 0;
}

.nav-title {
  color: #ffffff;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  padding: 1.2rem 1rem 0.6rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.15);
  opacity: 0.8;
}

.nav-link {
  transition: all 0.3s ease;
  color: #e4e7ed;
  padding: 0.85rem 1.2rem;
  border-radius: 12px;
  margin: 0.3rem 0.6rem;
  display: flex;
  align-items: center;
  font-size: 1rem;
}

.nav-link:hover {
  background: linear-gradient(90deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
  color: #ffffff;
  transform: translateX(8px) scale(1.02);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.nav-link.active {
  background: linear-gradient(90deg, #ffffff, #f0f0f0);
  color: #224abe;
  font-weight: 700;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  border-left: none;
  padding-left: 1.2rem;
}

.nav-icon {
  transition: transform 0.3s ease, color 0.3s ease;
  margin-right: 0.85rem;
  font-size: 1.2rem;
}

.nav-link:hover .nav-icon {
  transform: scale(1.3) rotate(5deg);
  color: #ffd700;
}

.sidebar-narrow .nav-link {
  justify-content: center;
  padding: 0.85rem;
}

.sidebar-narrow .nav-icon {
  margin-right: 0;
  font-size: 1.4rem;
}

.badge {
  transition: transform 0.3s ease, background-color 0.3s ease;
  font-weight: 600;
}

.nav-link:hover .badge {
  transform: scale(1.15);
  background-color: #ffd700;
  color: #224abe;
}
</style>
