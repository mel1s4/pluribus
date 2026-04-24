import { createRouter, createWebHistory } from 'vue-router'
import { hasCapability } from '../composables/useCapabilities'
import {
  clearHadAuthenticatedSession,
  hadAuthenticatedSessionMarker,
  resolveSession,
  sessionStatus,
} from '../composables/useSession'

const HomeView = () => import('../views/public/Home.vue')
const LoginView = () => import('../views/public/Login.vue')
const ContactView = () => import('../views/public/Contact.vue')
const LegalView = () => import('../views/public/Legal.vue')
const JoinInvitationView = () => import('../views/public/JoinInvitation.vue')
const DashboardView = () => import('../views/app/Dashboard.vue')
const SettingsView = () => import('../views/app/Settings.vue')
const ChatsView = () => import('../views/app/ChatsPage.vue')
const ChatThreadPage = () => import('../views/app/ChatThreadPage.vue')
const ChatFolderPage = () => import('../views/app/ChatFolderPage.vue')
const MapView = () => import('../views/app/MapView.vue')
const NotificationsView = () => import('../views/app/Notifications.vue')
const ProfileView = () => import('../views/app/Profile.vue')
const ApiTestView = () => import('../views/app/ApiTest.vue')
const UsersView = () => import('../views/app/Users.vue')
const UserCreatePage = () => import('../views/app/UserCreatePage.vue')
const UserEditPage = () => import('../views/app/UserEditPage.vue')
const MemberProfilePage = () => import('../views/app/MemberProfilePage.vue')
const MyPlacesView = () => import('../views/app/MyPlaces.vue')
const PlaceViewPage = () => import('../views/app/PlaceViewPage.vue')
const PlaceCreatePage = () => import('../views/app/PlaceCreatePage.vue')
const PlaceEditPage = () => import('../views/app/PlaceEditPage.vue')
const CommunitySettingsPage = () => import('../views/app/CommunitySettingsPage.vue')

const routes = [
  {
    path: '/',
    name: 'home',
    component: HomeView,
    meta: {
      layout: 'public',
    },
  },
  {
    path: '/contact',
    name: 'contact',
    component: ContactView,
    meta: {
      layout: 'public',
    },
  },
  {
    path: '/legal',
    name: 'legal',
    component: LegalView,
    meta: {
      layout: 'public',
    },
  },
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: {
      layout: 'public',
    },
  },
  {
    path: '/join/:token',
    alias: ['/invitacion/:token'],
    name: 'joinInvitation',
    component: JoinInvitationView,
    meta: {
      layout: 'public',
      headerTitleKey: 'joinInvitation.title',
    },
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: DashboardView,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'dashboard.title',
    },
  },
  {
    path: '/settings',
    name: 'settings',
    component: SettingsView,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'settings.title',
    },
  },
  {
    path: '/chats',
    name: 'chats',
    component: ChatsView,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'chats.title',
    },
  },
  {
    path: '/chats/folder/:folderId',
    name: 'chatFolder',
    component: ChatFolderPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'chats.folderTitle',
    },
  },
  {
    path: '/chats/:chatId',
    name: 'chatThread',
    component: ChatThreadPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: true,
      headerTitleKey: 'chats.thread.title',
    },
  },
  {
    path: '/map',
    name: 'map',
    component: MapView,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'map.title',
    },
  },
  {
    path: '/notifications',
    name: 'notifications',
    component: NotificationsView,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'notifications.title',
    },
  },
  {
    path: '/profile',
    name: 'profile',
    component: ProfileView,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'profile.title',
    },
  },
  {
    path: '/api-test',
    name: 'apiTest',
    component: ApiTestView,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'apiTest.title',
    },
  },
  {
    path: '/members/:userSlug',
    name: 'memberProfile',
    component: MemberProfilePage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'memberProfile.title',
    },
  },
  {
    path: '/places/:placeId',
    name: 'placeView',
    component: PlaceViewPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'places.viewTitle',
    },
  },
  {
    path: '/users/new',
    name: 'userCreate',
    component: UserCreatePage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      requiresCapability: 'users.create',
      hideHeader: false,
      headerTitleKey: 'users.createPageTitle',
    },
  },
  {
    path: '/users/:userId',
    name: 'userEdit',
    component: UserEditPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      requiresCapability: 'users.update',
      hideHeader: false,
      headerTitleKey: 'users.editPageTitle',
    },
  },
  {
    path: '/users',
    name: 'users',
    component: UsersView,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'users.title',
    },
  },
  {
    path: '/community',
    name: 'communitySettings',
    component: CommunitySettingsPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'communitySettings.title',
    },
  },
  {
    path: '/my-places/new',
    name: 'placeCreate',
    component: PlaceCreatePage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'myPlaces.createPageTitle',
    },
  },
  {
    path: '/my-places/:placeId',
    name: 'placeEdit',
    component: PlaceEditPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'myPlaces.editPageTitle',
    },
  },
  {
    path: '/my-places',
    name: 'myPlaces',
    component: MyPlacesView,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'myPlaces.title',
    },
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach(async (to) => {
  const needsResolution =
    sessionStatus.value === 'unknown'
    || (to.meta.requiresAuth && sessionStatus.value !== 'authenticated')

  if (needsResolution) {
    await resolveSession()
  }
  if (to.meta.requiresAuth && sessionStatus.value !== 'authenticated') {
    const query = { redirect: to.fullPath }
    if (hadAuthenticatedSessionMarker()) {
      query.sessionEnded = '1'
      clearHadAuthenticatedSession()
    }
    return { name: 'login', query }
  }
  if (to.name === 'login' && sessionStatus.value === 'authenticated') {
    return { name: 'dashboard' }
  }
  if (
    sessionStatus.value === 'authenticated'
    && to.meta.requiresCapability
    && typeof to.meta.requiresCapability === 'string'
    && !hasCapability(to.meta.requiresCapability)
  ) {
    return { name: 'dashboard' }
  }
  return true
})

export default router
