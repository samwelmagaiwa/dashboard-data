<template>
  <div class="loading-overlay">
    <div class="medical-loader">
      <!-- Heartbeat / EKG Animation -->
      <div class="heartbeat-container mb-4">
        <svg
          version="1.1"
          id="ekg"
          xmlns="http://www.w3.org/2000/svg"
          xmlns:xlink="http://www.w3.org/1999/xlink"
          x="0px"
          y="0px"
          viewBox="0 0 150 73"
          enable-background="new 0 0 150 73"
          xml:space="preserve"
          width="120px"
        >
          <polyline
            fill="none"
            class="ekg-line"
            stroke="#009688"
            stroke-width="3"
            stroke-miterlimit="10"
            points="0,45.486 9.255,45.486 38.17,45.486 42.195,45.486 52.32,20.53 58.565,60.895 65.59,0 72.84,54.895 78.84,45.486 150,45.486 "
          />
        </svg>
      </div>

      <h4 class="loading-title">Please wait while fetching data...</h4>
      <div class="loading-bar mt-3">
        <div class="loading-progress"></div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  z-index: 9999;
  background: rgba(255, 255, 255, 0.9);
  display: flex;
  justify-content: center;
  align-items: center;
  backdrop-filter: blur(4px);
}

.medical-loader {
  text-align: center;
  padding: 2rem;
}

/* EKG Animation */
.heartbeat-container {
  display: flex;
  justify-content: center;
}

.ekg-line {
  stroke-dasharray: 1000;
  stroke-dashoffset: 1000;
  animation: dash 2.5s linear infinite;
}

@keyframes dash {
  0% {
    stroke-dashoffset: 1000;
  }
  100% {
    stroke-dashoffset: 0;
  }
}

.loading-title {
  color: #2c3e50;
  font-weight: 600;
  font-size: 1.25rem;
  letter-spacing: 0.5px;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  margin-top: 10px;
}

/* Medical Progress Bar */
.loading-bar {
  width: 200px;
  height: 4px;
  background-color: #e9ecef;
  border-radius: 10px;
  margin: 0 auto;
  overflow: hidden;
}

.loading-progress {
  width: 50%;
  height: 100%;
  background-color: #009688; /* Medical Teal */
  border-radius: 10px;
  animation: progress-slide 1.5s infinite ease-in-out;
}

@keyframes progress-slide {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(200%);
  }
}
</style>
