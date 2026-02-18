<template>
  <div class="max-w-4xl mx-auto">
    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      <p class="mt-2 text-sm text-gray-600">Loading email settings...</p>
    </div>

    <div v-else class="space-y-6">
      <!-- Success/Error Messages -->
      <div v-if="saveSuccess" class="bg-green-50 border border-green-200 rounded p-3">
        <p class="text-sm text-green-800">&#10003; {{ saveSuccess }}</p>
      </div>
      <div v-if="saveError" class="bg-red-50 border border-red-200 rounded p-3">
        <p class="text-sm text-red-800">{{ saveError }}</p>
      </div>

      <!-- SMTP Configuration Card -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-gray-900">SMTP Settings</h2>
              <p class="text-sm text-gray-500 mt-1">
                Configure your email server for sending notifications
              </p>
            </div>
            <!-- Enable/Disable Toggle -->
            <label class="flex items-center cursor-pointer">
              <input
                v-model="settings.smtp_enabled"
                type="checkbox"
                class="sr-only peer"
              />
              <div class="relative w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
              <span class="ml-3 text-sm font-medium text-gray-900">
                {{ settings.smtp_enabled ? 'Enabled' : 'Disabled' }}
              </span>
            </label>
          </div>
        </div>

        <form @submit.prevent="saveSettings" class="px-6 py-6 space-y-6">
          <!-- Info Box -->
          <div class="bg-blue-50 border border-blue-200 rounded p-4">
            <div class="flex items-start gap-3">
              <span class="text-blue-600 text-xl flex-shrink-0">&#8505;&#65039;</span>
              <div class="flex-1 text-sm text-blue-800">
                <p class="font-medium mb-1">SMTP Configuration Required</p>
                <p>
                  WordPress uses PHP mail() by default, which often fails or goes to spam.
                  Configure SMTP for reliable email delivery.
                </p>
                <p class="mt-2">
                  <strong>Popular providers:</strong> Gmail (smtp.gmail.com:587),
                  SendGrid, Mailgun, Amazon SES
                </p>
              </div>
            </div>
          </div>

          <!-- SMTP Host and Port -->
          <div class="grid grid-cols-3 gap-4">
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                SMTP Host *
              </label>
              <input
                v-model="settings.smtp_host"
                type="text"
                required
                placeholder="smtp.gmail.com"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Port *
              </label>
              <input
                v-model.number="settings.smtp_port"
                type="number"
                required
                placeholder="587"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
          </div>

          <!-- Encryption -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Encryption
            </label>
            <select
              v-model="settings.smtp_encryption"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            >
              <option value="">None</option>
              <option value="tls">TLS (recommended)</option>
              <option value="ssl">SSL</option>
            </select>
            <p class="text-xs text-gray-500 mt-1">
              Use TLS for port 587, SSL for port 465
            </p>
          </div>

          <!-- Authentication -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Username *
              </label>
              <input
                v-model="settings.smtp_username"
                type="text"
                required
                placeholder="your-email@gmail.com"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Password *
              </label>
              <input
                v-model="settings.smtp_password"
                type="password"
                required
                placeholder="••••••••"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <p class="text-xs text-gray-500 mt-1">
                For Gmail, use an App Password, not your account password
              </p>
            </div>
          </div>

          <!-- From Name and Email -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                From Name *
              </label>
              <input
                v-model="settings.smtp_from_name"
                type="text"
                required
                placeholder="My Business"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                From Email *
              </label>
              <input
                v-model="settings.smtp_from_email"
                type="email"
                required
                placeholder="noreply@mybusiness.com"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <p class="text-xs text-gray-500 mt-1">
                Should match or be authorized by your SMTP host
              </p>
            </div>
          </div>

          <!-- Save Button -->
          <div class="flex justify-end pt-4 border-t border-gray-200">
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
            >
              {{ saving ? 'Saving...' : 'Save SMTP Settings' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Test Email Card -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Test Email</h2>
          <p class="text-sm text-gray-500 mt-1">
            Send a test email to verify your SMTP configuration is working
          </p>
        </div>

        <div class="px-6 py-6">
          <!-- Test Email Success -->
          <div v-if="testSuccess" class="mb-4 bg-green-50 border border-green-200 rounded p-3">
            <p class="text-sm text-green-800">&#10003; {{ testSuccess }}</p>
          </div>

          <!-- Test Email Error -->
          <div v-if="testError" class="mb-4 bg-red-50 border border-red-200 rounded p-3">
            <p class="text-sm text-red-800">{{ testError }}</p>
          </div>

          <!-- Warning if SMTP disabled -->
          <div v-if="!settings.smtp_enabled" class="mb-4 bg-amber-50 border border-amber-200 rounded p-3">
            <p class="text-sm text-amber-800">
              SMTP is currently disabled. Enable it above to use custom SMTP settings.
              Test email will use WordPress default (PHP mail).
            </p>
          </div>

          <form @submit.prevent="sendTestEmail" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Send Test Email To
              </label>
              <input
                v-model="testEmailAddress"
                type="email"
                required
                placeholder="your-email@example.com"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <p class="text-xs text-gray-500 mt-1">
                A test email will be sent to this address
              </p>
            </div>

            <div class="flex justify-end">
              <button
                type="submit"
                :disabled="sendingTest || !testEmailAddress"
                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
              >
                {{ sendingTest ? 'Sending...' : 'Send Test Email' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Help Card -->
      <div class="bg-gray-50 border border-gray-200 rounded-lg p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">
          Quick Setup Guides
        </h3>

        <div class="space-y-3 text-sm text-gray-700">
          <div>
            <p class="font-medium">Gmail:</p>
            <p class="text-xs text-gray-600">
              Host: smtp.gmail.com, Port: 587, Encryption: TLS<br />
              Use App Password (not account password):
              <a
                href="https://support.google.com/accounts/answer/185833"
                target="_blank"
                rel="noopener noreferrer"
                class="text-primary-600 hover:underline"
              >
                Create App Password
              </a>
            </p>
          </div>

          <div>
            <p class="font-medium">SendGrid:</p>
            <p class="text-xs text-gray-600">
              Host: smtp.sendgrid.net, Port: 587, Encryption: TLS<br />
              Username: apikey, Password: Your SendGrid API Key
            </p>
          </div>

          <div>
            <p class="font-medium">Mailgun:</p>
            <p class="text-xs text-gray-600">
              Host: smtp.mailgun.org, Port: 587, Encryption: TLS<br />
              Find credentials in Mailgun dashboard under Domain Settings
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '../composables/useApi'

const api = useApi()

const loading = ref(false)
const saving = ref(false)
const sendingTest = ref(false)
const saveSuccess = ref('')
const saveError = ref('')
const testSuccess = ref('')
const testError = ref('')
const testEmailAddress = ref('')

const settings = ref({
  smtp_enabled: false,
  smtp_host: '',
  smtp_port: 587,
  smtp_encryption: 'tls',
  smtp_username: '',
  smtp_password: '',
  smtp_from_name: '',
  smtp_from_email: ''
})

const SETTING_KEYS = 'smtp_enabled,smtp_host,smtp_port,smtp_encryption,smtp_username,smtp_password,smtp_from_name,smtp_from_email'

const loadSettings = async () => {
  loading.value = true

  try {
    const response = await api.get(`settings?keys=${SETTING_KEYS}`)

    if (response.data.success && response.data.settings) {
      Object.assign(settings.value, response.data.settings)
    }
  } catch (err) {
    saveError.value = 'Failed to load settings.'
  } finally {
    loading.value = false
  }
}

const saveSettings = async () => {
  saving.value = true
  saveSuccess.value = ''
  saveError.value = ''

  try {
    const response = await api.post('settings', {
      settings: settings.value
    })

    if (response.data.success) {
      saveSuccess.value = 'SMTP settings saved successfully.'

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

const sendTestEmail = async () => {
  sendingTest.value = true
  testSuccess.value = ''
  testError.value = ''

  try {
    const response = await api.post('settings/test-email', {
      to_email: testEmailAddress.value
    })

    if (response.data.success) {
      testSuccess.value = response.data.message

      setTimeout(() => {
        testSuccess.value = ''
      }, 5000)
    } else {
      testError.value = response.data.message || 'Failed to send test email.'
    }
  } catch (err) {
    testError.value = err.message || 'Failed to send test email. Check your SMTP settings.'
  } finally {
    sendingTest.value = false
  }
}

onMounted(() => {
  loadSettings()
})
</script>
