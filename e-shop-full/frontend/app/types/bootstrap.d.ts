declare module 'bootstrap/dist/js/bootstrap.bundle.min.js' {
  interface ComponentInstance {
    show(): void
    hide(): void
    toggle(): void
    dispose(): void
  }

  interface ComponentStatic {
    getInstance(element: Element | string): ComponentInstance | null
    getOrCreateInstance(element: Element | string, options?: Record<string, unknown>): ComponentInstance
  }

  const bootstrap: {
    Offcanvas: ComponentStatic
    Collapse: ComponentStatic
    Modal: ComponentStatic
    Dropdown: ComponentStatic
    Tooltip: ComponentStatic
  }

  export default bootstrap
}
