<template>
  <div class="max-w-4xl mx-auto space-y-6">
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
              :disabled="savingGeneral"
              @change="saveShowStaffEarnings"
            />
            <div class="relative w-11 h-6 bg-gray-200 rounded-full peer peer-disabled:opacity-50 peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
          </label>
        </div>
      </div>
    </div>

    <!-- Packages Settings -->
    <div v-if="isAdmin" class="bg-white rounded-xl border border-gray-200 p-6">
      <h2 class="text-base font-semibold text-gray-900 mb-1">Session Packages</h2>
      <p class="text-sm text-gray-500 mb-4">
        Allow customers to purchase prepaid session bundles and redeem them at booking.
      </p>

      <div class="flex items-center justify-between py-3 border-b border-gray-100">
        <div>
          <p id="packages-enabled-label" class="text-sm font-medium text-gray-900">Enable Session Packages</p>
          <p class="text-xs text-gray-500 mt-0.5">
            Shows package options at checkout and enables the Packages dashboard section.
          </p>
        </div>
        <label class="flex items-center cursor-pointer">
          <input
            id="packages-enabled-toggle"
            v-model="packagesEnabled"
            type="checkbox"
            class="sr-only peer"
            aria-labelledby="packages-enabled-label"
          />
          <div class="relative w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
        </label>
      </div>

      <div class="flex justify-end pt-4">
        <button
          type="button"
          :disabled="savingPackages"
          class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
          @click="savePackagesEnabled"
        >
          {{ savingPackages ? 'Saving...' : 'Save Package Settings' }}
        </button>
      </div>
    </div>

    <div v-if="isAdmin" class="bg-white rounded-lg shadow-sm border border-gray-200">
      <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">Branding</h2>
        <p class="text-sm text-gray-500 mt-1">
          Customize dashboard branding for your team.
        </p>
      </div>

      <div class="px-4 sm:px-6 py-6 space-y-6">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Logo</label>
          <div class="flex flex-wrap items-center gap-3">
            <button type="button" class="btn-secondary" @click="openMediaPicker">
              {{ branding.logoUrl ? 'Change logo' : 'Upload logo' }}
            </button>
            <button
              v-if="branding.logoUrl"
              type="button"
              class="btn-text"
              @click="removeLogo"
            >
              Remove logo
            </button>
          </div>
          <div v-if="branding.logoUrl" class="mt-3">
            <img :src="branding.logoUrl" alt="Brand logo preview" class="max-h-16 w-auto rounded object-contain border border-gray-200" />
          </div>
        </div>

        <div>
          <label for="branding-business-name" class="block text-sm font-medium text-gray-700 mb-1">
            Business Name
          </label>
          <input
            id="branding-business-name"
            v-model="branding.businessName"
            type="text"
            maxlength="100"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            placeholder="Your business name"
          />
          <p class="text-xs text-gray-500 mt-1">
            Shown in the dashboard header instead of 'Bookit'.
          </p>
        </div>

        <div>
          <label for="branding-primary-colour" class="block text-sm font-medium text-gray-700 mb-1">
            <span class="inline-flex items-center gap-1">
              Primary Colour
              <BookitTooltip
                content="Sets the main accent colour used in buttons and highlights throughout the booking widget."
                position="top"
              />
            </span>
          </label>
          <div class="flex items-center gap-3">
            <input
              id="branding-primary-colour"
              v-model="branding.primaryColour"
              type="color"
              class="h-10 w-14 p-1 border border-gray-300 rounded cursor-pointer"
              @input="syncHexFromColorPicker"
            />
            <input
              v-model="brandingHexInput"
              type="text"
              maxlength="7"
              class="w-40 px-3 py-2 border border-gray-300 rounded-lg uppercase focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              placeholder="#4F46E5"
              @input="syncColorFromHexInput"
            />
            <div class="flex items-center gap-2">
              <span class="h-7 w-7 rounded border border-gray-300" :style="{ backgroundColor: branding.primaryColour }"></span>
              <span class="text-xs text-gray-500">Preview</span>
            </div>
          </div>
          <p v-if="brandingHexError" class="text-xs text-red-600 mt-1">{{ brandingHexError }}</p>
        </div>

        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-sm font-medium text-gray-900 inline-flex items-center gap-1">
              Show 'Powered by Bookit'
              <BookitTooltip
                content="Shows or hides the 'Powered by Bookit' attribution in the customer-facing booking widget."
                position="top"
              />
            </p>
            <p class="text-sm text-gray-500 mt-1">
              Uncheck to hide the Bookit branding in the dashboard footer.
            </p>
          </div>
          <label class="flex items-center cursor-pointer">
            <input
              v-model="branding.poweredByVisible"
              type="checkbox"
              class="sr-only peer"
            />
            <div class="relative w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
          </label>
        </div>

        <div class="pt-4 border-t border-gray-200 flex justify-end">
          <button
            type="button"
            class="btn-primary"
            :disabled="savingBranding"
            @click="saveBranding"
          >
            {{ savingBranding ? 'Saving...' : 'Save Branding' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import { useApi } from '../composables/useApi'
import { useToast } from '../composables/useToast'
import { applyBranding, normalizeBranding } from '../utils/branding'

const api = useApi()
const { success: toastSuccess, error: toastError } = useToast()

const HEX_PATTERN = /^#[0-9A-Fa-f]{6}$/
const currentUserRole = window.BOOKIT_DASHBOARD?.staff?.role || ''
const isAdmin = computed(() => currentUserRole === 'admin' || currentUserRole === 'bookit_admin')

const showStaffEarnings = ref(false)
const packagesEnabled = ref(false)
const savingGeneral = ref(false)
const savingPackages = ref(false)
const savingBranding = ref(false)
const brandingHexInput = ref('#4F46E5')
const brandingHexError = ref('')

const branding = ref({
  logoUrl: '',
  primaryColour: '#4F46E5',
  businessName: '',
  poweredByVisible: true
})

const loadShowStaffEarnings = async () => {
  try {
    const response = await api.get('settings?keys=show_staff_earnings')

    if (response.data.success && response.data.settings) {
      showStaffEarnings.value = Boolean(response.data.settings.show_staff_earnings ?? false)
      return
    }
  } catch {
    // Fall back to the default value.
  }

  showStaffEarnings.value = false
}

const loadBranding = async () => {
  if (!isAdmin.value) {
    return
  }

  try {
    const response = await api.get('settings/branding')
    const normalized = normalizeBranding(response.data || {})
    branding.value = normalized
    brandingHexInput.value = normalized.primaryColour
    brandingHexError.value = ''
  } catch (err) {
    toastError(err.message || 'Failed to load branding settings.')
  }
}

const loadPackagesEnabled = async () => {
  try {
    const response = await api.get('settings?keys=packages_enabled')
    if (response.data.success && response.data.settings) {
      packagesEnabled.value = response.data.settings.packages_enabled === '1' ||
                              response.data.settings.packages_enabled === true ||
                              response.data.settings.packages_enabled === 1
    }
  } catch {
    // Fall back to false
  }
}

const saveShowStaffEarnings = async () => {
  savingGeneral.value = true

  try {
    const response = await api.post('settings', {
      settings: {
        show_staff_earnings: showStaffEarnings.value
      }
    })

    if (response.data.success) {
      toastSuccess('Settings saved successfully.')
    } else {
      toastError(response.data.message || 'Failed to save settings.')
    }
  } catch (err) {
    toastError(err.message || 'Failed to save settings.')
  } finally {
    savingGeneral.value = false
  }
}

const savePackagesEnabled = async () => {
  savingPackages.value = true
  try {
    const response = await api.post('settings', {
      settings: { packages_enabled: packagesEnabled.value ? '1' : '0' }
    })
    if (response.data.success) {
      toastSuccess('Package settings saved.')
    } else {
      toastError(response.data.message || 'Failed to save package settings.')
    }
  } catch (err) {
    toastError(err.message || 'Failed to save package settings.')
  } finally {
    savingPackages.value = false
  }
}

const syncHexFromColorPicker = () => {
  brandingHexInput.value = branding.value.primaryColour.toUpperCase()
  brandingHexError.value = ''
}

const syncColorFromHexInput = () => {
  brandingHexInput.value = brandingHexInput.value.toUpperCase()

  if (!HEX_PATTERN.test(brandingHexInput.value)) {
    brandingHexError.value = 'Use a valid hex value in #RRGGBB format.'
    return
  }

  brandingHexError.value = ''
  branding.value.primaryColour = brandingHexInput.value
}

const removeLogo = () => {
  branding.value.logoUrl = ''
}

const openMediaPicker = () => {
  if (!window.wp || !window.wp.media) {
    toastError('WordPress media library is not available.')
    return
  }

  const frame = window.wp.media({
    title: 'Select Logo',
    button: { text: 'Use this image' },
    multiple: false,
    library: { type: 'image' }
  })

  frame.on('select', () => {
    const attachment = frame.state().get('selection').first().toJSON()
    branding.value.logoUrl = attachment?.url || ''
  })

  frame.open()
}

const saveBranding = async () => {
  if (!HEX_PATTERN.test(brandingHexInput.value)) {
    brandingHexError.value = 'Use a valid hex value in #RRGGBB format.'
    return
  }

  savingBranding.value = true
  brandingHexError.value = ''

  try {
    const payload = {
      branding_logo_url: branding.value.logoUrl || '',
      branding_primary_colour: brandingHexInput.value.toUpperCase(),
      branding_business_name: branding.value.businessName || '',
      branding_powered_by_visible: Boolean(branding.value.poweredByVisible)
    }

    const response = await api.patch('settings/branding', payload)

    if (!response.data?.success) {
      toastError(response.data?.message || 'Failed to save branding settings.')
      return
    }

    const updatedBranding = normalizeBranding(response.data.branding || payload)
    branding.value = updatedBranding
    brandingHexInput.value = updatedBranding.primaryColour

    applyBranding(updatedBranding)
    window.BOOKIT_DASHBOARD.branding = { ...updatedBranding }
    window.dispatchEvent(new CustomEvent('bookit:branding-updated', { detail: updatedBranding }))

    toastSuccess('Branding settings saved successfully.')
  } catch (err) {
    toastError(err.message || 'Failed to save branding settings.')
  } finally {
    savingBranding.value = false
  }
}

onMounted(async () => {
  await loadShowStaffEarnings()
  await loadPackagesEnabled()
  await loadBranding()
})
</script>
