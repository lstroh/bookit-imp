<template>
  <div class="max-w-4xl mx-auto space-y-6">
    <!-- Success/Error Messages -->
    <div v-if="saveSuccess" class="bg-green-50 border border-green-200 rounded p-3">
      <p class="text-sm text-green-800">&#10003; {{ saveSuccess }}</p>
    </div>
    <div v-if="saveError" class="bg-red-50 border border-red-200 rounded p-3">
      <p class="text-sm text-red-800">{{ saveError }}</p>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
      <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">Staff</h2>
        <p class="text-sm text-gray-500 mt-1">
          Configure staff dashboard visibility settings
        </p>
      </div>

      <div class="px-4 sm:px-6 py-6">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-sm font-medium text-gray-900">Show earnings to staff members</p>
            <p class="text-sm text-gray-500 mt-1">
              When enabled, staff can see their own booking count and revenue on their profile page
            </p>
          </div>

          <label class="flex items-center cursor-pointer">
            <input
              v-model="showStaffEarnings"
              type="checkbox"
              class="sr-only peer"
              :disabled="saving"
              @change="saveShowStaffEarnings"
            />
            <div class="relative w-11 h-6 bg-gray-200 rounded-full peer peer-disabled:opacity-50 peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
          </label>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '../composables/useApi'

const api = useApi()

const showStaffEarnings = ref(false)
const saving = ref(false)
const saveSuccess = ref('')
const saveError = ref('')

const loadShowStaffEarnings = async () => {
  try {
    const response = await api.get('settings?keys=show_staff_earnings')

    if (response.data.success && response.data.settings) {
      showStaffEarnings.value = Boolean(response.data.settings.show_staff_earnings ?? false)
    } else {
      showStaffEarnings.value = false
    }
  } catch (err) {
    showStaffEarnings.value = false
    saveError.value = 'Failed to load settings.'
  }
}

const saveShowStaffEarnings = async () => {
  saving.value = true
  saveSuccess.value = ''
  saveError.value = ''

  try {
    const response = await api.post('settings', {
      settings: {
        show_staff_earnings: showStaffEarnings.value
      }
    })

    if (response.data.success) {
      saveSuccess.value = 'Settings saved successfully.'
      setTimeout(() => {
        saveSuccess.value = ''
      }, 3000)
    } else {
      saveError.value = response.data.message || 'Failed to save settings.'
    }
  } catch (err) {
    saveError.value = err.message || 'Failed to save settings.'
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadShowStaffEarnings()
})
</script>
