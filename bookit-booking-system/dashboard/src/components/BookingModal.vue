<template>
  <!-- Modal Backdrop -->
  <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <!-- Modal Content -->
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
      <!-- Header -->
      <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between sticky top-0 bg-white z-10">
        <h2 class="text-xl font-semibold text-gray-900">
          Create New Booking
        </h2>
        <button
          @click="$emit('close')"
          class="text-gray-400 hover:text-gray-600 text-2xl leading-none"
        >
          &times;
        </button>
      </div>

      <!-- Body -->
      <div class="px-6 py-4">
        <!-- Step 1: Customer Selection -->
        <div v-if="currentStep === 1">
          <CustomerSelector v-model="bookingData.customer" />
        </div>

        <!-- Step 2-5: Placeholder for Part C -->
        <div v-else-if="currentStep === 2">
          <p class="text-gray-600">Step 2: Service selection will be implemented in Part C</p>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 border-t border-gray-200 flex justify-between bg-gray-50 sticky bottom-0">
        <button
          v-if="currentStep > 1"
          @click="currentStep--"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
        >
          &larr; Back
        </button>
        <div v-else></div>

        <div class="flex gap-2">
          <button
            @click="$emit('close')"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            v-if="currentStep === 1"
            :disabled="!bookingData.customer"
            @click="nextStep"
            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Next: Select Service &rarr;
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import CustomerSelector from './CustomerSelector.vue'

defineEmits(['close', 'created'])

const currentStep = ref(1)
const bookingData = ref({
  customer: null,
  service: null,
  staff: null,
  dateTime: null,
  payment: null
})

const nextStep = () => {
  if (currentStep.value < 5) {
    currentStep.value++
  }
}
</script>
