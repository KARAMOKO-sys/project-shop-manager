export type ToastVariant = 'success' | 'danger' | 'warning' | 'info'

export interface Toast {
  id: number
  message: string
  variant: ToastVariant
}

let toastSequence = 0

export function useToast() {
  const toasts = useState<Toast[]>('toasts', () => [])

  function dismiss(id: number): void {
    toasts.value = toasts.value.filter(toast => toast.id !== id)
  }

  function show(message: string, variant: ToastVariant = 'success', duration = 4500): void {
    const id = ++toastSequence
    toasts.value = [...toasts.value, { id, message, variant }]

    if (import.meta.client) {
      setTimeout(() => dismiss(id), duration)
    }
  }

  return {
    toasts,
    show,
    dismiss,
    success: (message: string) => show(message, 'success'),
    error: (message: string) => show(message, 'danger', 6000),
  }
}
