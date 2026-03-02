<template>
  <div>
    <div class="mb-6">
      <h2 class="text-lg font-semibold text-gray-900">
        Team Calendar
      </h2>
    </div>

    <div v-if="loading" class="space-y-3">
      <CardSkeleton v-for="i in 4" :key="i" />
    </div>

    <ErrorState
      v-else-if="error"
      :title="errorTitle"
      :message="errorMessage"
      :details="errorDetails"
      :show-home="false"
      @retry="fetchCalendar"
    />

    <EmptyState
      v-else-if="staff.length === 0"
      icon="👥"
      title="No staff members found"
      description="Add at least one active staff member to view the team calendar."
    />

    <div v-else>
      <div class="flex flex-wrap items-center gap-3 mb-4">
        <button
          class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          aria-label="Go to previous period"
          @click="prevPeriod"
        >
          &larr; Prev
        </button>
        <button
          class="px-3 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
          aria-label="Go to today"
          @click="goToday"
        >
          Today
        </button>
        <button
          class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          aria-label="Go to next period"
          @click="nextPeriod"
        >
          Next &rarr;
        </button>

        <p class="text-sm font-medium text-gray-700 min-w-0">
          {{ periodLabel }}
        </p>

        <div class="ml-auto flex items-center gap-2">
          <button
            class="px-3 py-1.5 text-sm font-medium border rounded-lg transition-colors"
            :class="currentView === 'day' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
            @click="currentView = 'day'"
          >
            Day
          </button>
          <button
            class="px-3 py-1.5 text-sm font-medium border rounded-lg transition-colors"
            :class="currentView === 'week' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
            @click="currentView = 'week'"
          >
            Week
          </button>
          <button
            disabled
            title="Month view coming soon"
            class="px-3 py-1.5 text-sm font-medium border rounded-lg bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed"
          >
            Month
          </button>
        </div>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="max-h-[72vh] overflow-auto">
          <div :style="{ minWidth: `${80 + columns.length * minColumnWidth}px` }">
            <div class="grid sticky top-0 z-30 bg-white border-b border-gray-200" :style="gridTemplateStyle">
              <div class="sticky left-0 z-40 bg-white border-r border-gray-200 p-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                Time
              </div>
              <div
                v-for="column in columns"
                :key="column.key"
                class="p-2 border-r border-gray-200"
                :class="column.isToday ? 'bg-primary-50' : 'bg-white'"
              >
                <div v-if="currentView === 'day'" class="flex items-center gap-2 min-w-0">
                  <img
                    v-if="column.photoUrl"
                    :src="column.photoUrl"
                    :alt="column.label"
                    class="w-8 h-8 rounded-full object-cover flex-shrink-0"
                  />
                  <div
                    v-else
                    class="w-8 h-8 rounded-full text-white text-xs font-semibold flex items-center justify-center flex-shrink-0"
                    :style="{ backgroundColor: column.colour }"
                  >
                    {{ column.initials }}
                  </div>
                  <span class="text-sm font-medium text-gray-900 truncate">{{ column.label }}</span>
                </div>
                <div v-else>
                  <p class="text-sm font-semibold text-gray-900 truncate">
                    {{ column.label }}
                  </p>
                  <p class="text-xs text-gray-500">
                    {{ formatShortDate(column.date) }}
                  </p>
                </div>
              </div>
            </div>

            <div class="grid" :style="gridTemplateStyle">
              <div class="sticky left-0 z-20 bg-white border-r border-gray-200" :style="{ height: `${gridHeight}px` }">
                <div
                  v-for="slot in slots"
                  :key="`time-${slot.minutes}`"
                  class="absolute left-0 right-0 border-t"
                  :class="slot.isHour ? 'border-gray-300' : 'border-gray-100'"
                  :style="{ top: `${slot.offset}px` }"
                >
                  <span
                    v-if="slot.isHour"
                    class="absolute -top-2 left-2 bg-white px-1 text-xs font-medium text-gray-500"
                  >
                    {{ slot.label }}
                  </span>
                </div>
              </div>

              <div
                v-for="column in columns"
                :key="`grid-${column.key}`"
                class="relative border-r border-gray-200"
                :class="column.isToday ? 'bg-primary-50/50' : 'bg-white'"
                :style="{ height: `${gridHeight}px` }"
              >
                <div
                  v-for="slot in slots"
                  :key="`${column.key}-slot-${slot.minutes}`"
                  class="absolute left-0 right-0 border-t pointer-events-none"
                  :class="slot.isHour ? 'border-gray-300' : 'border-gray-100'"
                  :style="{ top: `${slot.offset}px` }"
                />

                <button
                  v-for="block in column.timeOff"
                  :key="`timeoff-${column.key}-${block.staff_id}-${block.start_time || 'all'}-${block.label}`"
                  type="button"
                  class="absolute left-1 right-1 rounded-md border border-dashed border-gray-400 bg-gray-100/80 p-2 text-left text-xs text-gray-700 overflow-hidden"
                  :style="getTimeOffStyle(block)"
                >
                  <p class="font-semibold truncate">{{ block.label || 'Time Off' }}</p>
                  <p v-if="!block.all_day && block.start_time && block.end_time" class="text-[11px] text-gray-600">
                    {{ block.start_time }} - {{ block.end_time }}
                  </p>
                </button>

                <button
                  v-for="booking in column.bookings"
                  :key="`booking-${column.key}-${booking.id}`"
                  type="button"
                  class="absolute left-1 right-1 rounded-md p-2 text-left text-xs overflow-hidden border-l-4 focus:outline-none focus:ring-2 focus:ring-primary-500"
                  :style="getBookingStyle(booking)"
                  @click="openBookingDetails(booking, column.date)"
                >
                  <div class="flex items-start justify-between gap-2">
                    <p class="font-semibold text-gray-900 truncate">{{ booking.customer_name }}</p>
                    <span class="px-1.5 py-0.5 text-[10px] font-medium rounded-full flex-shrink-0" :class="getStatusClass(booking.status)">
                      {{ formatStatus(booking.status) }}
                    </span>
                  </div>

                  <p v-if="showSecondaryLine(booking)" class="text-gray-700 truncate mt-0.5">
                    {{ currentView === 'week' ? `${getStaffName(booking.staff_id)} · ${booking.service_name}` : booking.service_name }}
                  </p>
                  <p v-if="showTimeLine(booking)" class="text-gray-600 mt-0.5">
                    {{ booking.start_time }} - {{ booking.end_time }}
                  </p>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="selectedBooking" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/30" @click.self="selectedBooking = null">
      <div class="w-full max-w-md bg-white rounded-lg shadow-xl border border-gray-200 p-4">
        <div class="flex items-start justify-between gap-3 mb-3">
          <h3 class="text-base font-semibold text-gray-900">Booking Details</h3>
          <button
            type="button"
            class="text-gray-500 hover:text-gray-700"
            aria-label="Close booking details"
            @click="selectedBooking = null"
          >
            &times;
          </button>
        </div>
        <div class="space-y-2 text-sm">
          <p><span class="font-medium text-gray-700">Customer:</span> {{ selectedBooking.customer_name }}</p>
          <p><span class="font-medium text-gray-700">Service:</span> {{ selectedBooking.service_name }}</p>
          <p><span class="font-medium text-gray-700">Staff:</span> {{ getStaffName(selectedBooking.staff_id) }}</p>
          <p><span class="font-medium text-gray-700">Date:</span> {{ selectedBooking.booking_date }}</p>
          <p><span class="font-medium text-gray-700">Time:</span> {{ selectedBooking.start_time }} - {{ selectedBooking.end_time }}</p>
          <p><span class="font-medium text-gray-700">Status:</span> {{ formatStatus(selectedBooking.status) }}</p>
          <p><span class="font-medium text-gray-700">Payment:</span> {{ selectedBooking.payment_status }}</p>
          <p><span class="font-medium text-gray-700">Price:</span> £{{ Number(selectedBooking.total_price || 0).toFixed(2) }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useApi } from '../composables/useApi'
import ErrorState from '../components/ErrorState.vue'
import CardSkeleton from '../components/CardSkeleton.vue'
import EmptyState from '../components/EmptyState.vue'

const SLOT_HEIGHT = 40
const MINUTES_PER_SLOT = 30

const api = useApi()

const currentView = ref('day')
const currentDate = ref(new Date())

const loading = ref(true)
const error = ref(null)
const errorTitle = ref('')
const errorMessage = ref('')
const errorDetails = ref('')

const dateStart = ref('')
const dateEnd = ref('')
const staff = ref([])
const days = ref([])
const selectedBooking = ref(null)

const minColumnWidth = computed(() => (currentView.value === 'day' ? 220 : 240))
const gridTemplateStyle = computed(() => ({
  gridTemplateColumns: `80px repeat(${columns.value.length}, minmax(${minColumnWidth.value}px, 1fr))`
}))

const staffMap = computed(() => {
  const map = {}
  for (const member of staff.value) {
    map[member.id] = member
  }
  return map
})

const dayLookup = computed(() => {
  const map = {}
  for (const day of days.value) {
    map[day.date] = day
  }
  return map
})

const periodLabel = computed(() => {
  if (!dateStart.value || !dateEnd.value) return ''
  if (currentView.value === 'day') {
    return formatLongDate(dateStart.value)
  }

  const start = new Date(`${dateStart.value}T12:00:00`)
  const end = new Date(`${dateEnd.value}T12:00:00`)
  const startDay = new Intl.DateTimeFormat('en-GB', { day: 'numeric' }).format(start)
  const endPart = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }).format(end)
  return `${startDay}–${endPart}`
})

const currentDateKey = computed(() => dateToYMD(currentDate.value))

const columns = computed(() => {
  if (currentView.value === 'day') {
    const day = days.value[0] || { bookings: [], time_off: [] }
    return staff.value.map(member => ({
      key: `staff-${member.id}`,
      date: day.date || dateStart.value,
      label: member.full_name,
      initials: member.initials,
      colour: member.colour,
      photoUrl: member.photo_url,
      isToday: !!day.is_today,
      bookings: day.bookings.filter(b => Number(b.staff_id) === Number(member.id)),
      timeOff: day.time_off.filter(t => Number(t.staff_id) === Number(member.id))
    }))
  }

  return days.value.map(day => ({
    key: `day-${day.date}`,
    label: day.label,
    date: day.date,
    isToday: day.is_today,
    bookings: day.bookings,
    timeOff: day.time_off
  }))
})

const timeBounds = computed(() => {
  let minMinutes = Number.POSITIVE_INFINITY
  let maxMinutes = Number.NEGATIVE_INFINITY

  for (const column of columns.value) {
    for (const booking of column.bookings) {
      minMinutes = Math.min(minMinutes, timeToMinutes(booking.start_time))
      maxMinutes = Math.max(maxMinutes, timeToMinutes(booking.end_time))
    }
    for (const block of column.timeOff) {
      if (!block.all_day && block.start_time && block.end_time) {
        minMinutes = Math.min(minMinutes, timeToMinutes(block.start_time))
        maxMinutes = Math.max(maxMinutes, timeToMinutes(block.end_time))
      }
    }
  }

  if (!Number.isFinite(minMinutes) || !Number.isFinite(maxMinutes)) {
    return { start: 8 * 60, end: 20 * 60 }
  }

  const start = Math.floor(minMinutes / 30) * 30
  const end = Math.ceil(maxMinutes / 30) * 30
  const adjustedEnd = end <= start ? start + 60 : end
  return { start, end: adjustedEnd }
})

const slots = computed(() => {
  const result = []
  const start = timeBounds.value.start
  const end = timeBounds.value.end
  for (let minutes = start; minutes <= end; minutes += MINUTES_PER_SLOT) {
    result.push({
      minutes,
      offset: ((minutes - start) / MINUTES_PER_SLOT) * SLOT_HEIGHT,
      label: minutesToTime(minutes),
      isHour: minutes % 60 === 0
    })
  }
  return result
})

const gridHeight = computed(() => {
  const totalSlots = (timeBounds.value.end - timeBounds.value.start) / MINUTES_PER_SLOT
  return Math.max(totalSlots * SLOT_HEIGHT, SLOT_HEIGHT * 4)
})

function dateToYMD(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function addDays(date, daysToAdd) {
  const next = new Date(date.getTime())
  next.setDate(next.getDate() + daysToAdd)
  return next
}

function formatLongDate(dateString) {
  const date = new Date(`${dateString}T12:00:00`)
  return new Intl.DateTimeFormat('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  }).format(date)
}

function formatShortDate(dateString) {
  const date = new Date(`${dateString}T12:00:00`)
  return new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short'
  }).format(date)
}

function prevPeriod() {
  currentDate.value = addDays(currentDate.value, currentView.value === 'day' ? -1 : -7)
}

function nextPeriod() {
  currentDate.value = addDays(currentDate.value, currentView.value === 'day' ? 1 : 7)
}

function goToday() {
  currentDate.value = new Date()
}

async function fetchCalendar() {
  loading.value = true
  error.value = null

  try {
    const response = await api.get(`${window.BOOKIT_DASHBOARD.restBase}team-calendar`, {
      params: {
        view_type: currentView.value,
        date: currentDateKey.value
      }
    })

    if (!response.data?.success) {
      throw new Error(response.data?.message || 'Failed to load team calendar')
    }

    dateStart.value = response.data.date_start
    dateEnd.value = response.data.date_end
    staff.value = Array.isArray(response.data.staff) ? response.data.staff : []
    days.value = Array.isArray(response.data.days) ? response.data.days : []
  } catch (err) {
    console.error('Error loading team calendar:', err)
    error.value = true

    if (err.status === 403) {
      errorTitle.value = 'Access denied'
      errorMessage.value = 'Only administrators can access the team calendar.'
    } else if (err.status >= 500) {
      errorTitle.value = 'Server error'
      errorMessage.value = 'Our servers are experiencing issues. Please try again in a few moments.'
    } else if (!navigator.onLine) {
      errorTitle.value = 'No internet connection'
      errorMessage.value = 'Please check your internet connection and try again.'
    } else {
      errorTitle.value = 'Failed to load team calendar'
      errorMessage.value = err.message || 'An unexpected error occurred.'
    }

    errorDetails.value = `Error: ${err.message}\nStatus: ${err.status || 'N/A'}`
  } finally {
    loading.value = false
  }
}

function timeToMinutes(timeString) {
  const [h, m] = String(timeString).split(':').map(Number)
  return (h * 60) + m
}

function minutesToTime(totalMinutes) {
  const h = String(Math.floor(totalMinutes / 60)).padStart(2, '0')
  const m = String(totalMinutes % 60).padStart(2, '0')
  return `${h}:${m}`
}

function toRgba(hex, alpha) {
  const normal = String(hex || '').replace('#', '')
  if (!/^[0-9a-fA-F]{6}$/.test(normal)) return `rgba(79, 70, 229, ${alpha})`
  const r = parseInt(normal.slice(0, 2), 16)
  const g = parseInt(normal.slice(2, 4), 16)
  const b = parseInt(normal.slice(4, 6), 16)
  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

function getBookingStyle(booking) {
  const staffColour = staffMap.value[booking.staff_id]?.colour || '#4F46E5'
  const startMinutes = timeToMinutes(booking.start_time)
  const endMinutes = timeToMinutes(booking.end_time)
  const top = (startMinutes - timeBounds.value.start) * (SLOT_HEIGHT / MINUTES_PER_SLOT)
  const height = Math.max((endMinutes - startMinutes) * (SLOT_HEIGHT / MINUTES_PER_SLOT), 24)

  return {
    top: `${top}px`,
    height: `${height}px`,
    backgroundColor: toRgba(staffColour, 0.18),
    borderLeftColor: staffColour
  }
}

function getTimeOffStyle(block) {
  if (block.all_day) {
    return { top: '0px', height: `${gridHeight.value}px` }
  }

  const startMinutes = timeToMinutes(block.start_time)
  const endMinutes = timeToMinutes(block.end_time)
  const top = (startMinutes - timeBounds.value.start) * (SLOT_HEIGHT / MINUTES_PER_SLOT)
  const height = Math.max((endMinutes - startMinutes) * (SLOT_HEIGHT / MINUTES_PER_SLOT), 24)
  return {
    top: `${top}px`,
    height: `${height}px`
  }
}

function getStatusClass(status) {
  const classes = {
    confirmed: 'bg-green-100 text-green-800',
    pending: 'bg-yellow-100 text-yellow-800',
    pending_payment: 'bg-orange-100 text-orange-800',
    completed: 'bg-blue-100 text-blue-800',
    cancelled: 'bg-red-100 text-red-800',
    no_show: 'bg-gray-100 text-gray-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

function formatStatus(status) {
  const labels = {
    pending: 'Pending',
    pending_payment: 'Pending Payment',
    confirmed: 'Confirmed',
    completed: 'Completed',
    cancelled: 'Cancelled',
    no_show: 'No Show'
  }
  return labels[status] || status
}

function getStaffName(staffId) {
  return staffMap.value[staffId]?.full_name || 'Unknown staff'
}

function bookingDurationMinutes(booking) {
  return Math.max(timeToMinutes(booking.end_time) - timeToMinutes(booking.start_time), 0)
}

function showSecondaryLine(booking) {
  if (currentView.value === 'week') {
    return bookingDurationMinutes(booking) >= 20
  }
  return bookingDurationMinutes(booking) >= 40
}

function showTimeLine(booking) {
  if (currentView.value === 'week') {
    return bookingDurationMinutes(booking) >= 35
  }
  return bookingDurationMinutes(booking) >= 55
}

function openBookingDetails(booking, bookingDate) {
  selectedBooking.value = {
    ...booking,
    booking_date: bookingDate || dateStart.value
  }
}

watch([currentView, currentDateKey], fetchCalendar)

onMounted(fetchCalendar)
</script>
