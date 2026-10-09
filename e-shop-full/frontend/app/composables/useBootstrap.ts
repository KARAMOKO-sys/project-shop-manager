import type bootstrap from 'bootstrap/dist/js/bootstrap.bundle.min.js'

/** Bootstrap JS API (client only; undefined during SSR). */
export function useBootstrap(): typeof bootstrap | undefined {
  return useNuxtApp().$bootstrap as typeof bootstrap | undefined
}

/** Hides an offcanvas / collapse when the route changes (e.g. mobile menus). */
export function useCloseOnNavigate(selector: string, component: 'Offcanvas' | 'Collapse'): void {
  const route = useRoute()

  watch(() => route.fullPath, () => {
    const element = import.meta.client ? document.querySelector(selector) : null

    if (element) {
      useBootstrap()?.[component].getInstance(element)?.hide()
    }
  })
}
