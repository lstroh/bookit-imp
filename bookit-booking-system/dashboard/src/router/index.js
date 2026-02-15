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
    path: '/settings',
    name: 'settings',
    component: () => import('../views/Settings.vue'),
    meta: { title: 'Settings' }
  }
]
