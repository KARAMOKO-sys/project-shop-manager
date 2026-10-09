<script setup lang="ts">
import type { Order, OrderStatus, Paginated, Resource, Shop } from '~/types'

const props = defineProps<{ shop: Shop }>()
const api = useApi()
const toast = useToast()

const page = ref(1)
const expanded = ref<number | null>(null)
const updating = ref<number | null>(null)

const { data: orders, status, error, refresh } = useAsyncData(
  `shop-orders-${props.shop.id}`,
  () => api<Paginated<Order>>(`/shops/${props.shop.id}/orders`, { query: { page: page.value } }),
  { server: false, watch: [page], deep: true },
)

async function changeStatus(order: Order, next: OrderStatus): Promise<void> {
  const previous = order.status
  order.status = next
  updating.value = order.id

  try {
    const { data } = await api<Resource<Order>>(`/shops/${props.shop.id}/orders/${order.id}`, { method: 'PATCH', body: { status: next } })
    Object.assign(order, data)
    toast.success(`Commande ${order.order_number} : ${orderStatusMeta(next).label.toLowerCase()}.`)
  }
  catch (requestError) {
    order.status = previous
    toast.error(validationErrors(requestError).status ?? errorMessage(requestError, 'Le statut n’a pas pu être modifié.'))
  }
  finally {
    updating.value = null
  }
}
</script>

<template>
  <div class="card">
    <div class="card-body">
      <h2 class="h5 fw-bold mb-0">Commandes <span v-if="orders?.meta" class="text-muted fw-normal">({{ orders.meta.total }})</span></h2>
    </div>
    <AppState v-if="status === 'pending' || status === 'idle'" loading text="Chargement des commandes…" />
    <AppState v-else-if="error" variant="error" title="Impossible de charger les commandes" :text="errorMessage(error)">
      <button type="button" class="btn btn-primary" @click="refresh()">Réessayer</button>
    </AppState>
    <AppState v-else-if="!orders?.data.length" icon="bi-receipt" title="Aucune commande pour l’instant" text="Partagez le lien de votre boutique sur WhatsApp et les réseaux sociaux pour recevoir vos premières commandes." />
    <div v-else class="table-responsive">
      <table class="table align-middle mb-0">
        <caption class="visually-hidden">Commandes de la boutique</caption>
        <thead class="table-light">
          <tr>
            <th scope="col">Commande</th>
            <th scope="col">Client</th>
            <th scope="col">Total</th>
            <th scope="col">Paiement</th>
            <th scope="col">Statut</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="order in orders.data" :key="order.id">
            <tr>
              <td>
                <button
                  type="button"
                  class="btn btn-link p-0 fw-semibold text-decoration-none"
                  :aria-expanded="expanded === order.id"
                  :aria-controls="`order-${order.id}`"
                  @click="expanded = expanded === order.id ? null : order.id"
                >
                  #{{ order.order_number }} <i class="bi bi-chevron-down small" aria-hidden="true" />
                </button>
                <p class="small text-muted mb-0">{{ formatDate(order.created_at) }}</p>
              </td>
              <td>
                <p class="mb-0">{{ order.customer.first_name }} {{ order.customer.last_name }}</p>
                <a :href="`tel:${order.customer.phone}`" class="small">{{ order.customer.phone }}</a>
              </td>
              <td class="text-nowrap"><PriceTag :amount="order.total" :currency="order.currency" /></td>
              <td>
                <p class="small mb-0">{{ paymentMethodLabel(order.payment_method) }}</p>
                <span class="small text-muted">{{ order.payment_status }}</span>
              </td>
              <td style="min-width: 11rem;">
                <span v-if="!ORDER_TRANSITIONS[order.status]?.length" class="badge rounded-pill" :class="`text-bg-${orderStatusMeta(order.status).variant}`">
                  {{ orderStatusMeta(order.status).label }}
                </span>
                <template v-else>
                  <label :for="`status-${order.id}`" class="visually-hidden">Statut de la commande {{ order.order_number }}</label>
                  <select
                    :id="`status-${order.id}`"
                    class="form-select form-select-sm"
                    :value="order.status"
                    :disabled="updating === order.id"
                    @change="changeStatus(order, ($event.target as HTMLSelectElement).value as OrderStatus)"
                  >
                    <option v-for="option in allowedOrderStatuses(order.status)" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                </template>
              </td>
            </tr>
            <tr v-if="expanded === order.id" :id="`order-${order.id}`">
              <td colspan="5" class="bg-light">
                <div class="row g-3">
                  <div class="col-md-7">
                    <p class="small fw-semibold mb-1">Articles</p>
                    <ul class="list-unstyled small mb-0">
                      <li v-for="item in order.items" :key="item.product_id" class="d-flex justify-content-between gap-2">
                        <span>{{ item.quantity }} × {{ item.product_name }}</span>
                        <PriceTag :amount="item.total_price" :currency="order.currency" />
                      </li>
                    </ul>
                  </div>
                  <div class="col-md-5 small">
                    <p class="fw-semibold mb-1">Récapitulatif</p>
                    <div class="d-flex justify-content-between"><span>Sous-total</span><PriceTag :amount="order.subtotal" :currency="order.currency" /></div>
                    <div class="d-flex justify-content-between"><span>Livraison</span><PriceTag :amount="order.shipping_amount" :currency="order.currency" /></div>
                    <div v-if="order.discount_amount" class="d-flex justify-content-between"><span>Remise</span><span>−{{ formatPrice(order.discount_amount, order.currency) }}</span></div>
                    <p class="mt-2 mb-0"><i class="bi bi-envelope me-1" aria-hidden="true" />{{ order.customer.email }}</p>
                  </div>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
    <div class="px-3 pb-3">
      <AppPagination v-model:page="page" :meta="orders?.meta" />
    </div>
  </div>
</template>
