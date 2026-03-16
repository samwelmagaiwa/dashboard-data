<script setup>
const props = defineProps({
  href: String,
})

import ComponentsImg from '@/assets/images/components.webp'
</script>

<template>
  <div class="bg-primary bg-opacity-10 border border-2 border-primary rounded mb-4">
    <div class="row d-flex align-items-center p-3 px-xl-4 flex-xl-nowrap">
      <div class="col-xl-auto col-12 d-none d-xl-block p-0">
        <img
          class="img-fluid"
          :src="ComponentsImg"
          width="160px"
          height="160px"
          alt="MNH Components"
        />
      </div>
      <div class="col-md col-12 px-lg-4">
        Muhimbili National Hospital Dashboard is built with customized components tailored for hospital administration. These components support features like patient management, bed allocation, staff attendance, and more, ensuring efficient operations within the hospital environment.
      </div>
      <div class="col-md-auto col-12 mt-3 mt-lg-0">
        <a
          class="btn btn-primary text-nowrap text-white"
          href="https://mnh.or.tz/"
          target="_blank"
          rel="noopener noreferrer"
        >
          Explore Hospital Documentation
        </a>
      </div>
    </div>
  </div>
</template>
