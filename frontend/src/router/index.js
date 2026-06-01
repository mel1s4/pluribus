import { createRouter, createWebHistory } from 'vue-router'
import { hasCapability, isVisitorUser } from '../composables/useCapabilities'
import {
  communityHostSlug,
  isCommunityHostSite,
} from '../composables/useCommunityHost'
import { COMMUNITY_HOST_ALLOWED_ROUTE_NAMES } from '../navigation/communityHostNav'
import {
  clearHadAuthenticatedSession,
  hadAuthenticatedSessionMarker,
  resolveSession,
  sessionUser,
  sessionStatus,
} from '../composables/useSession'

const HomeView = () => import('../views/public/Home.vue')
const LoginView = () => import('../views/public/Login.vue')
const ContactView = () => import('../views/public/Contact.vue')
const LegalView = () => import('../views/public/Legal.vue')
const CommunityLegalPublicView = () => import('../views/public/CommunityLegalPublicPage.vue')
const JoinInvitationView = () => import('../views/public/JoinInvitation.vue')
const VisitorAuthConsumeView = () => import('../views/public/VisitorAuthConsume.vue')
const ForgotPasswordView = () => import('../views/public/ForgotPassword.vue')
const ResetPasswordView = () => import('../views/public/ResetPassword.vue')
const TableAccessView = () => import('../views/public/TableAccessPage.vue')
const DashboardView = () => import('../views/app/Dashboard.vue')
const SettingsView = () => import('../views/app/Settings.vue')
const MyContactsPage = () => import('../views/app/MyContactsPage.vue')
const ChatsPage = () => import('../views/app/ChatsPage.vue')
const ChatThreadPage = () => import('../views/app/ChatThreadPage.vue')
const ChatInfoPage = () => import('../views/app/ChatInfoPage.vue')
const MapPage = () => import('../views/public/MapPage.vue')
const FoldersPage = () => import('../views/app/FoldersPage.vue')
const FolderDetailPage = () => import('../views/app/FolderDetailPage.vue')
const NoteDetailPage = () => import('../views/app/NoteDetailPage.vue')
const CalendarPage = () => import('../views/app/CalendarPage.vue')
const PostsPage = () => import('../views/app/PostsPage.vue')
const PostDetailPage = () => import('../views/app/PostDetailPage.vue')
const PostComposerPage = () => import('../views/app/PostComposerPage.vue')
const SurveysPage = () => import('../views/app/SurveysPage.vue')
const SurveyDetailPage = () => import('../views/app/SurveyDetailPage.vue')
const SurveyComposerPage = () => import('../views/app/SurveyComposerPage.vue')
const MyGroupsPage = () => import('../views/app/MyGroups.vue')
const GroupDetailPage = () => import('../views/app/GroupDetailPage.vue')
const NotificationsView = () => import('../views/app/Notifications.vue')
const ProfileView = () => import('../views/app/Profile.vue')
const ApiTestView = () => import('../views/app/ApiTest.vue')
const UsersView = () => import('../views/app/Users.vue')
const SupportPersonificationPage = () => import('../views/app/SupportPersonificationPage.vue')
const UserCreatePage = () => import('../views/app/UserCreatePage.vue')
const UserEditPage = () => import('../views/app/UserEditPage.vue')
const MemberProfilePage = () => import('../views/app/MemberProfilePage.vue')
const MyPlacesView = () => import('../views/app/MyPlaces.vue')
const PlaceViewPage = () => import('../views/app/PlaceViewPage.vue')
const PlacePublicPage = () => import('../views/app/PlacePublicPage.vue')
const PlaceCreatePage = () => import('../views/app/PlaceCreatePage.vue')
const PlaceEditPage = () => import('../views/app/PlaceEditPage.vue')
const PlaceTableDetailPage = () => import('../views/app/PlaceTableDetailPage.vue')
const PlaceLiveOrdersPage = () => import('../views/app/PlaceLiveOrdersPage.vue')
const PlaceOrderDetailPage = () => import('../views/app/PlaceOrderDetailPage.vue')
const PlaceOfferCreatePage = () => import('../views/app/PlaceOfferCreatePage.vue')
const PlaceOfferEditPage = () => import('../views/app/PlaceOfferEditPage.vue')
const CommunitySettingsPage = () => import('../views/app/CommunitySettingsPage.vue')
const CommunityMicrositePage = () => import('../views/app/CommunityMicrositePage.vue')
const CommunityCreditsPage = () => import('../views/app/CommunityCreditsPage.vue')
const CommunityProjectsPage = () => import('../views/app/CommunityProjectsPage.vue')
const CommunityProjectDetailPage = () => import('../views/app/CommunityProjectDetailPage.vue')
const MyProjectsPage = () => import('../views/app/MyProjectsPage.vue')
const CommunityMembershipManagementPage = () =>
  import('../views/app/CommunityMembershipManagementPage.vue')
const CommunitiesPage = () => import('../views/app/CommunitiesPage.vue')
const CommunityCreatePage = () => import('../views/app/CommunityCreatePage.vue')
const CommunityEditPage = () => import('../views/app/CommunityEditPage.vue')
const MyCommunitiesPage = () => import('../views/app/MyCommunitiesPage.vue')
const CartPage = () => import('../views/app/CartPage.vue')
const OrdersPage = () => import('../views/app/OrdersPage.vue')
const OrderDetailPage = () => import('../views/app/OrderDetailPage.vue')
const MyWalletPage = () => import('../views/app/MyWalletPage.vue')
const WalletSendPage = () => import('../views/app/WalletSendPage.vue')
const WalletMovementDetailPage = () => import('../views/app/WalletMovementDetailPage.vue')

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
    path: '/legal/community/:communitySlug([a-z0-9-]+)/:document(terms|privacy)',
    name: 'communityLegalPublic',
    component: CommunityLegalPublicView,
    meta: {
      layout: 'public',
      headerTitleKey: 'communityLegalPublic.headerTitle',
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
    path: '/join/:token/verify/:verifyToken',
    alias: ['/invitacion/:token/verify/:verifyToken'],
    name: 'joinInvitationVerify',
    component: JoinInvitationView,
    meta: {
      layout: 'public',
      headerTitleKey: 'joinInvitation.title',
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
    path: '/forgot-password',
    name: 'forgotPassword',
    component: ForgotPasswordView,
    meta: {
      layout: 'public',
    },
  },
  {
    path: '/reset-password/:token',
    name: 'resetPassword',
    component: ResetPasswordView,
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
    path: '/community/:communitySlug([a-z0-9-]+)/dashboard',
    name: 'dashboardScoped',
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
    path: '/:communitySlug([a-z0-9-]+)/dashboard',
    name: 'dashboardScopedLegacy',
    redirect: (to) => ({
      name: 'dashboardScoped',
      params: { communitySlug: to.params.communitySlug },
      query: to.query,
      hash: to.hash,
    }),
    meta: {
      layout: 'app',
      requiresAuth: true,
    },
  },
  {
    path: '/:communitySlug([a-z0-9-]+)/community-settings/:tab?',
    name: 'communitySettingsScoped',
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
    path: '/community/dashboard',
    name: 'dashboardCommunityLegacy',
    redirect: () => ({ name: 'dashboard' }),
    meta: {
      layout: 'app',
      requiresAuth: true,
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
    path: '/support/personification',
    name: 'supportPersonification',
    component: SupportPersonificationPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      requiresCapability: 'users.personify',
      hideHeader: false,
      headerTitleKey: 'personification.pageTitle',
      sidebarKey: 'support-personification',
    },
  },
  {
    path: '/chats',
    name: 'chats',
    component: ChatsPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'chats.title',
      sidebarKey: 'chats',
    },
  },
  {
    path: '/my-contacts',
    name: 'myContacts',
    component: MyContactsPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'contacts.title',
      sidebarKey: 'my-contacts',
    },
  },
  {
    path: '/chats/folder/:folderId',
    name: 'chatFolder',
    redirect: (to) => ({ name: 'folderDetail', params: { folderId: to.params.folderId } }),
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
    component: MapPage,
    meta: {
      layout: 'public',
      headerTitleKey: 'map.title',
      sidebarKey: 'map',
    },
  },
  {
    path: '/tasks',
    name: 'tasks',
    redirect: () => ({ name: 'folders', query: { focus: 'tasks' } }),
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
    path: '/notes/:noteId',
    name: 'noteDetail',
    component: NoteDetailPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'notes.detailTitle',
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
    path: '/posts/:id',
    name: 'posts-detail',
    component: PostDetailPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'posts.detailPageTitle',
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
    path: '/surveys/new',
    name: 'surveys-new',
    component: SurveyComposerPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'surveys.composerCreateTitle',
      sidebarKey: 'surveys',
      requiresCapability: 'surveys.manage',
    },
  },
  {
    path: '/surveys/:id/edit',
    name: 'surveys-edit',
    component: SurveyComposerPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'surveys.composerEditTitle',
      sidebarKey: 'surveys',
      requiresCapability: 'surveys.manage',
    },
  },
  {
    path: '/surveys/:id',
    name: 'surveys-detail',
    component: SurveyDetailPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'surveys.detailPageTitle',
      sidebarKey: 'surveys',
      requiresCapability: 'surveys.view',
    },
  },
  {
    path: '/surveys',
    name: 'surveys',
    component: SurveysPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'surveys.title',
      sidebarKey: 'surveys',
      requiresCapability: 'surveys.view',
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
      requiresCapability: 'notifications.view',
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
    path: '/community',
    redirect: '/community-settings',
  },
  {
    path: '/community/leadership',
    redirect: '/community-settings/leadership',
  },
  {
    path: '/community/settings',
    redirect: '/community-settings/settings',
  },
  {
    path: '/community/:slug([a-z0-9-]+)/members',
    name: 'communityMemberships',
    component: CommunityMembershipManagementPage,
    beforeEnter: communityMembershipsBeforeEnter,
    meta: {
      layout: 'app',
      requiresAuth: true,
      requiresCapability: 'community.memberships.manage',
      hideHeader: false,
      headerTitleKey: 'communityMemberships.title',
    },
  },
  {
    path: '/community/:slug([a-z0-9-]+)/credits',
    name: 'communityCredits',
    component: CommunityCreditsPage,
    meta: {
      layout: 'app',
      requiresAuth: false,
      hideHeader: false,
      headerTitleKey: 'communityCredits.pageTitle',
    },
  },
  {
    path: '/my-projects',
    name: 'myProjects',
    component: MyProjectsPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'communityProjects.myProjectsTitle',
      sidebarKey: 'my-projects',
    },
  },
  {
    path: '/community/:slug([a-z0-9-]+)/projects/:projectId(\\d+)',
    name: 'communityProjectDetail',
    component: CommunityProjectDetailPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'communityProjects.detailTitle',
    },
  },
  {
    path: '/community/:slug([a-z0-9-]+)/projects',
    name: 'communityProjects',
    component: CommunityProjectsPage,
    meta: {
      layout: 'app',
      requiresAuth: false,
      hideHeader: false,
      headerTitleKey: 'communityProjects.listTitle',
    },
  },
  {
    path: '/community/:slug([a-z0-9-]+)/settings/:tab?',
    name: 'communitySettingsBySlug',
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
    path: '/community/:slug([a-z0-9-]+)',
    name: 'communityMicrosite',
    component: CommunityMicrositePage,
    meta: {
      layout: 'app',
      requiresAuth: false,
      hideHeader: false,
      headerTitleKey: 'communityMicrosite.title',
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
    path: '/users/invitations',
    redirect: { name: 'users' },
  },
  {
    path: '/users',
    name: 'users',
    component: UsersView,
    meta: {
      layout: 'app',
      requiresAuth: true,
      requiresCapability: 'users.view',
      hideHeader: false,
      headerTitleKey: 'users.title',
      sidebarKey: 'users',
    },
  },
  {
    path: '/:communitySlug([a-z0-9-]+)/communities/new',
    name: 'communityCreateScoped',
    component: CommunityCreatePage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      requiresCapability: 'communities.manage',
      hideHeader: false,
      headerTitleKey: 'communities.createPageTitle',
      sidebarKey: 'communities',
    },
  },
  {
    path: '/:communitySlug([a-z0-9-]+)/communities/:communityId(\\d+)/edit',
    name: 'communityEditScoped',
    component: CommunityEditPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      requiresCapability: 'communities.view',
      hideHeader: false,
      headerTitleKey: 'communities.editPageTitle',
      sidebarKey: 'communities',
    },
  },
  {
    path: '/:communitySlug([a-z0-9-]+)/communities',
    name: 'communitiesScoped',
    component: CommunitiesPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      requiresCapability: 'communities.view',
      hideHeader: false,
      headerTitleKey: 'communities.title',
      sidebarKey: 'communities',
    },
  },
  {
    path: '/communities/new',
    name: 'communityCreate',
    component: CommunityCreatePage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      requiresCapability: 'communities.manage',
      hideHeader: false,
      headerTitleKey: 'communities.createPageTitle',
      sidebarKey: 'communities',
    },
  },
  {
    path: '/communities/:communityId(\\d+)/edit',
    name: 'communityEdit',
    component: CommunityEditPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      requiresCapability: 'communities.view',
      hideHeader: false,
      headerTitleKey: 'communities.editPageTitle',
      sidebarKey: 'communities',
    },
  },
  {
    path: '/communities',
    name: 'communities',
    component: CommunitiesPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      requiresCapability: 'communities.view',
      hideHeader: false,
      headerTitleKey: 'communities.title',
      sidebarKey: 'communities',
    },
  },
  {
    path: '/community-settings/:tab?',
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
    path: '/my-communities',
    name: 'myCommunities',
    component: MyCommunitiesPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'myCommunities.title',
      sidebarKey: 'my-communities',
    },
  },
  {
    path: '/:communitySlug([a-z0-9-]+)/wallet/send',
    name: 'walletSendScoped',
    component: WalletSendPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      requiresCapability: 'wallet.view',
      hideHeader: false,
      headerTitleKey: 'wallet.sendPageTitle',
      sidebarKey: 'my-wallet',
    },
  },
  {
    path: '/:communitySlug([a-z0-9-]+)/wallet/movements/:transactionId(\\d+)',
    name: 'walletMovementScoped',
    component: WalletMovementDetailPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      requiresCapability: 'wallet.view',
      hideHeader: false,
      headerTitleKey: 'wallet.movementDetailTitle',
      sidebarKey: 'my-wallet',
    },
  },
  {
    path: '/:communitySlug([a-z0-9-]+)/wallet',
    name: 'walletScoped',
    component: MyWalletPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      requiresCapability: 'wallet.view',
      hideHeader: false,
      headerTitleKey: 'wallet.title',
      sidebarKey: 'my-wallet',
    },
  },
  {
    path: '/wallet/send',
    name: 'walletSend',
    component: WalletSendPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      requiresCapability: 'wallet.view',
      hideHeader: false,
      headerTitleKey: 'wallet.sendPageTitle',
      sidebarKey: 'my-wallet',
    },
  },
  {
    path: '/wallet/movements/:transactionId(\\d+)',
    name: 'walletMovement',
    component: WalletMovementDetailPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      requiresCapability: 'wallet.view',
      hideHeader: false,
      headerTitleKey: 'wallet.movementDetailTitle',
      sidebarKey: 'my-wallet',
    },
  },
  {
    path: '/wallet',
    name: 'wallet',
    component: MyWalletPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      requiresCapability: 'wallet.view',
      hideHeader: false,
      headerTitleKey: 'wallet.title',
      sidebarKey: 'my-wallet',
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
    path: '/my-places/:placeId/offers/:offerId/edit',
    name: 'placeOfferEdit',
    component: PlaceOfferEditPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'myPlaces.editOfferPageTitle',
    },
  },
  {
    path: '/my-places/:placeId/live-orders',
    name: 'placeLiveOrders',
    component: PlaceLiveOrdersPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: true,
      headerTitleKey: 'orders.liveViewTitle',
    },
  },
  {
    path: '/my-places/:placeId/orders/:orderId',
    name: 'placeOrderDetail',
    component: PlaceOrderDetailPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'orders.detailTitle',
    },
  },
  {
    path: '/my-places/:placeId/tables/:tableId',
    name: 'placeTableDetail',
    component: PlaceTableDetailPage,
    meta: {
      layout: 'app',
      requiresAuth: true,
      hideHeader: false,
      headerTitleKey: 'myPlaces.tableDetailTitle',
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

function dashboardFallbackRoute(to) {
  if (isCommunityHostSite.value && communityHostSlug.value) {
    return {
      name: 'dashboardScoped',
      params: { communitySlug: communityHostSlug.value },
    }
  }
  const scopedSlug = typeof to.params?.communitySlug === 'string' ? to.params.communitySlug : ''
  if (scopedSlug) {
    return { name: 'dashboardScoped', params: { communitySlug: scopedSlug } }
  }
  return { name: 'dashboard' }
}

function hasCommunityMembership(user) {
  return Number(user?.community_count || 0) > 0
}

function communityMembershipsBeforeEnter(to) {
  const slug = typeof to.params.slug === 'string' ? to.params.slug.trim() : ''
  if (!slug) {
    return { name: 'dashboard' }
  }
  const u = sessionUser.value
  if (!u) {
    return true
  }
  if (u.is_root) {
    return true
  }
  const list = Array.isArray(u.communities) ? u.communities : []
  const row = list.find((c) => c && String(c.slug || '').trim() === slug)
  if (row && row.role === 'admin') {
    return true
  }
  return { name: 'dashboard' }
}

router.beforeEach(async (to) => {
  if (isCommunityHostSite.value && communityHostSlug.value) {
    const slug = communityHostSlug.value
    const routeName = String(to.name || '')
    if (to.name === 'home') {
      return { name: 'communityMicrosite', params: { slug } }
    }
    if (routeName && !COMMUNITY_HOST_ALLOWED_ROUTE_NAMES.has(routeName)) {
      if (to.meta?.requiresAuth) {
        return { name: 'dashboardScoped', params: { communitySlug: slug } }
      }
      return { name: 'communityMicrosite', params: { slug } }
    }

    const paramSlug = to.params.slug ?? to.params.communitySlug
    if (paramSlug && String(paramSlug).trim() !== slug) {
      const nextParams = { ...to.params }
      if ('slug' in nextParams) nextParams.slug = slug
      if ('communitySlug' in nextParams) nextParams.communitySlug = slug
      return {
        name: to.name,
        params: nextParams,
        query: to.query,
        hash: to.hash,
        replace: true,
      }
    }
  }

  const requiresAuth = Boolean(to.meta.requiresAuth)
  const unknownSession = sessionStatus.value === 'unknown'
  const needsResolution =
    (requiresAuth && sessionStatus.value !== 'authenticated')
    || (unknownSession && requiresAuth)

  if (needsResolution) {
    await resolveSession()
  }
  if (unknownSession && !requiresAuth) {
    // Resolve in background so public first paint/navigation is never blocked.
    resolveSession().catch(() => {})
  }
  if (to.meta.requiresAuth && sessionStatus.value !== 'authenticated') {
    const query = { redirect: to.fullPath }
    if (hadAuthenticatedSessionMarker()) {
      query.sessionEnded = '1'
      clearHadAuthenticatedSession()
    }
    return { name: 'login', query }
  }
  if (
    sessionStatus.value === 'authenticated'
    && to.meta.requiresCapability
    && typeof to.meta.requiresCapability === 'string'
    && !hasCapability(to.meta.requiresCapability)
  ) {
    return dashboardFallbackRoute(to)
  }
  if (
    sessionStatus.value === 'authenticated'
    && !sessionUser.value?.is_root
    && !hasCommunityMembership(sessionUser.value)
    && ![
      'profile',
      'settings',
      'myCommunities',
      'joinInvitation',
      'joinInvitationVerify',
      'login',
      'dashboard',
      'dashboardScoped',
      'wallet',
      'walletScoped',
      'walletSend',
      'walletSendScoped',
      'walletMovement',
      'walletMovementScoped',
      'communityMicrosite',
      'communityCredits',
      'communityProjects',
      'communityProjectDetail',
      'myProjects',
      'communityMemberships',
      'communities',
      'communitiesScoped',
      'communityCreate',
      'communityEdit',
      'communityCreateScoped',
      'communityEditScoped',
      'map',
    ].includes(String(to.name || ''))
  ) {
    if (isCommunityHostSite.value && communityHostSlug.value) {
      return {
        name: 'communityMicrosite',
        params: { slug: communityHostSlug.value },
      }
    }
    return { name: 'myCommunities' }
  }
  if (
    sessionStatus.value === 'authenticated'
    && isVisitorUser()
    && ![
      'dashboard',
      'dashboardScoped',
      'map',
      'placePublic',
      'communityMicrosite',
      'communityCredits',
      'communityProjects',
      'cart',
      'orders',
      'orderDetail',
      'profile',
      'settings',
      'visitorAuthConsume',
    ].includes(String(to.name || ''))
  ) {
    return dashboardFallbackRoute(to)
  }
  return true
})

export default router
