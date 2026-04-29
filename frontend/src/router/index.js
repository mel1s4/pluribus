import { createRouter, createWebHistory } from 'vue-router'
import { hasCapability, isCommunityAdministrator, isVisitorUser } from '../composables/useCapabilities'
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
const VisitorAuthConsumeView = () => import('../views/public/VisitorAuthConsume.vue')
const TableAccessView = () => import('../views/public/TableAccessPage.vue')
const DashboardView = () => import('../views/app/Dashboard.vue')
const SettingsView = () => import('../views/app/Settings.vue')
const ChatsView = () => import('../views/app/ChatsPage.vue')
const ChatThreadPage = () => import('../views/app/ChatThreadPage.vue')
const ChatInfoPage = () => import('../views/app/ChatInfoPage.vue')
const FolderPage = () => import('../views/app/ChatFolderPage.vue')
const MapView = () => import('../views/app/MapView.vue')
const TasksPage = () => import('../views/app/TasksPage.vue')
const FoldersPage = () => import('../views/app/FoldersPage.vue')
const FolderDetailPage = () => import('../views/app/FolderDetailPage.vue')
const CalendarPage = () => import('../views/app/CalendarPage.vue')
const PostsPage = () => import('../views/app/PostsPage.vue')
const PostComposerPage = () => import('../views/app/PostComposerPage.vue')
const MyGroupsPage = () => import('../views/app/MyGroups.vue')
const GroupDetailPage = () => import('../views/app/GroupDetailPage.vue')
const NotificationsView = () => import('../views/app/Notifications.vue')
const ProfileView = () => import('../views/app/Profile.vue')
const ApiTestView = () => import('../views/app/ApiTest.vue')
const UsersView = () => import('../views/app/Users.vue')
const UserCreatePage = () => import('../views/app/UserCreatePage.vue')
const UserEditPage = () => import('../views/app/UserEditPage.vue')
const MemberProfilePage = () => import('../views/app/MemberProfilePage.vue')
const MyPlacesView = () => import('../views/app/MyPlaces.vue')
const PlaceViewPage = () => import('../views/app/PlaceViewPage.vue')
const PlacePublicPage = () => import('../views/app/PlacePublicPage.vue')
const PlaceCreatePage = () => import('../views/app/PlaceCreatePage.vue')
const PlaceEditPage = () => import('../views/app/PlaceEditPage.vue')
const PlaceOfferCreatePage = () => import('../views/app/PlaceOfferCreatePage.vue')
const CommunitySettingsPage = () => import('../views/app/CommunitySettingsPage.vue')
const CartPage = () => import('../views/app/CartPage.vue')
const OrdersPage = () => import('../views/app/OrdersPage.vue')
const OrderDetailPage = () => import('../views/app/OrderDetailPage.vue')

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
    path: '/visitor-auth/:token',
    name: 'visitorAuthConsume',
    component: VisitorAuthConsumeView,
    meta: {
      layout: 'public',
    },
  },
  {
    path: '/table-access/:token',
    name: 'tableAccess',
    component: TableAccessView,
    meta: {
      layout: 'public',
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
      sidebarKey: 'dashboard',
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
      sidebarKey: 'settings',
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
      sidebarKey: 'chats',
    },
  },
  {
    path: '/chats/folder/:folderId',
    name: 'chatFolder',
    component: FolderPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'chats.folderTitle',
    },
  },
  {
    path: '/chats/:chatId/info',
    name: 'chatInfo',
    component: ChatInfoPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'chats.info.title',
    },
  },
  {
    path: '/chats/:chatId',
    name: 'chatThread',
    component: ChatThreadPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'chats.thread.title',
    },
  },
  {
    path: '/map/:placeId?/:tab?',
    name: 'map',
    component: MapView,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'map.title',
      sidebarKey: 'map',
    },
  },
  {
    path: '/tasks',
    name: 'tasks',
    component: TasksPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'tasks.title',
      sidebarKey: 'tasks',
    },
  },
  {
    path: '/folders',
    name: 'folders',
    component: FoldersPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'folders.title',
      sidebarKey: 'folders',
    },
  },
  {
    path: '/folders/:folderId',
    name: 'folderDetail',
    component: FolderDetailPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'folders.detail',
      sidebarKey: 'folders',
    },
  },
  {
    path: '/calendar',
    name: 'calendar',
    component: CalendarPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'calendar.title',
      sidebarKey: 'calendar',
    },
  },
  {
    path: '/posts/new',
    name: 'posts-new',
    component: PostComposerPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'posts.composerCreateTitle',
      sidebarKey: 'posts',
    },
  },
  {
    path: '/posts/:id/edit',
    name: 'posts-edit',
    component: PostComposerPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'posts.composerEditTitle',
      sidebarKey: 'posts',
    },
  },
  {
    path: '/posts',
    name: 'posts',
    component: PostsPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'posts.title',
      sidebarKey: 'posts',
    },
  },
  {
    path: '/my-groups',
    name: 'myGroups',
    component: MyGroupsPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'groups.title',
      sidebarKey: 'my-groups',
    },
  },
  {
    path: '/my-groups/:groupId',
    name: 'groupDetail',
    component: GroupDetailPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'groups.title',
      sidebarKey: 'my-groups',
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
      sidebarKey: 'notifications',
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
      sidebarKey: 'profile',
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
    path: '/place/:slug',
    name: 'placePublic',
    component: PlacePublicPage,
    meta: {
      layout: 'app',
      requiresAuth: false,
      hideHeader: false,
      headerTitleKey: 'places.storefrontPageTitle',
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
    path: '/users/:userId(\\d+)',
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
    path: '/users/:tab?',
    name: 'users',
    component: UsersView,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'users.title',
      sidebarKey: 'users',
    },
  },
  {
    path: '/community/:tab?',
    name: 'communitySettings',
    component: CommunitySettingsPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'communitySettings.title',
      sidebarKey: 'community-settings',
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
    path: '/my-places/:placeId/offers/new',
    name: 'placeOfferCreate',
    component: PlaceOfferCreatePage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'myPlaces.addOfferPageTitle',
    },
  },
  {
    path: '/my-places/:placeId/:tab?',
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
      sidebarKey: 'my-places',
    },
  },
  {
    path: '/cart',
    name: 'cart',
    component: CartPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'cart.title',
      sidebarKey: 'my-cart',
    },
  },
  {
    path: '/orders',
    name: 'orders',
    component: OrdersPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'orders.title',
      sidebarKey: 'orders',
    },
  },
  {
    path: '/orders/:orderId',
    name: 'orderDetail',
    component: OrderDetailPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'orders.detailTitle',
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
  if (sessionStatus.value === 'authenticated' && to.name === 'users' && !isCommunityAdministrator()) {
    return { name: 'dashboard' }
  }
  if (
    sessionStatus.value === 'authenticated'
    && isVisitorUser()
    && !['dashboard', 'map', 'placePublic', 'cart', 'orders', 'orderDetail', 'profile', 'settings', 'visitorAuthConsume'].includes(String(to.name || ''))
  ) {
    return { name: 'dashboard' }
  }
  return true
})

export default router
