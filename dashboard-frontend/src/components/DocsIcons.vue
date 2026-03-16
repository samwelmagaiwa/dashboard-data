<script setup>
import IconsImg from '@/assets/images/icons.webp'
</script>

<template>
  <div class="bg-warning bg-opacity-10 border border-2 border-warning rounded mb-4">
    <div class="row d-flex align-items-center p-3 px-xl-4 flex-xl-nowrap">
      <div class="col-xl-auto col-12 d-none d-xl-block p-0">
        <img class="img-fluid" :src="IconsImg" width="160px" height="160px" alt="MNH Icons" />
      </div>
      <div class="col-md col-12 px-lg-4">
        Muhimbili National Hospital Dashboard utilizes a customized set of icons tailored for medical and administrative functions. These icons, based on CoreUI Icons, include symbols for patients, beds, attendance, and hospital-specific operations to enhance the user interface for hospital staff.
      </div>
      <div class="col-md-auto col-12 mt-3 mt-lg-0">
        <a
          class="btn btn-warning text-nowrap text-white"
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
