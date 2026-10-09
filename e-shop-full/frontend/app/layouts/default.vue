<template>
  <div class="d-flex flex-column min-vh-100">
    <AppNavbar />
    <main id="contenu" class="flex-grow-1">
      <slot />
    </main>
    <AppFooter />
  </div>
</template>
