export default [
  {
    path: '/',
    name: 'dashboard',
    component: () => import('../views/Dashboard.vue'),
    meta: { title: "Today's Schedule" }
  },
  {
    path: '/bookings',
    name: 'bookings',
    component: () => import('../views/Bookings.vue'),
    meta: { title: 'Bookings' }
  },
  {
    path: '/services',
    name: 'services',
    component: () => import('../views/Services.vue'),
    meta: { title: 'Services' }
  },
  {
    path: '/categories',
    name: 'categories',
    component: () => import('../views/Categories.vue'),
    meta: { title: 'Categories' }
  },
  {
    path: '/staff',
    name: 'staff',
    component: () => import('../views/Staff.vue'),
    meta: { title: 'Staff' }
  },
  {
    path: '/staff/:staff_id/hours',
    name: 'StaffHours',
    component: () => import('../views/StaffHours.vue'),
    meta: { title: 'Working Hours' }
  },
  {
    path: '/settings',
    name: 'settings',
    component: () => import('../views/Settings.vue'),
    meta: { title: 'Settings' }
  },
  {
    path: '/profile',
    name: 'MyProfile',
    component: () => import('../views/MyProfile.vue'),
    meta: { title: 'My Profile' }
  },
  {
    path: '/settings/email',
    name: 'EmailSettings',
    component: () => import('../views/EmailSettings.vue'),
    meta: { title: 'Email Configuration', requiresAdmin: true }
  },
  {
    path: '/settings/templates',
    name: 'EmailTemplates',
    component: () => import('../views/EmailTemplates.vue'),
    meta: { title: 'Email Templates', requiresAdmin: true }
  }
]
