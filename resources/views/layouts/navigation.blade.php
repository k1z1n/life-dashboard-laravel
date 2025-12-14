<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <!-- Notifications bell -->
                <div x-data="notificationBell()" x-init="init()" class="relative me-2">
                    <button @click="toggle()" type="button"
                            class="relative inline-flex items-center justify-center w-10 h-10 rounded-full text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0a3 3 0 11-6 0h6z"/>
                        </svg>

                        <template x-if="unreadCount > 0">
                            <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white text-[11px] leading-[18px] text-center">
                                <span x-text="unreadCount > 99 ? '99+' : unreadCount"></span>
                            </span>
                        </template>
                    </button>

                    <!-- Dropdown -->
                    <div x-show="open" x-transition @click.outside="open=false"
                         class="absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden z-50"
                         style="display: none;">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <div class="text-sm font-semibold text-gray-800">Уведомления</div>
                            <div class="text-xs text-gray-500" x-text="unreadCount ? `Новых: ${unreadCount}` : 'Нет новых'"></div>
                        </div>

                        <div class="max-h-96 overflow-y-auto">
                            <template x-if="items.length === 0">
                                <div class="px-4 py-6 text-sm text-gray-500 text-center">Пока нет уведомлений</div>
                            </template>

                            <template x-for="n in items" :key="n.id">
                                <a :href="n.url || '#'"
                                   @mouseenter="markRead(n)"
                                   @click.prevent="onItemClick(n)"
                                   class="block px-4 py-3 border-b border-gray-100 hover:bg-gray-50 transition"
                                   :class="n.read_at ? 'opacity-70' : ''">
                                    <div class="flex items-start gap-2">
                                        <div class="mt-1 w-2 h-2 rounded-full"
                                             :class="n.read_at ? 'bg-gray-300' : 'bg-indigo-600'"></div>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm font-medium text-gray-900 truncate" x-text="n.title || 'Уведомление'"></div>
                                            <div class="text-sm text-gray-600 break-words whitespace-pre-wrap" x-text="n.body || ''"></div>
                                            <div class="mt-1 text-xs text-gray-400" x-text="n.created_human || ''"></div>
                                        </div>
                                    </div>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>

<script>
    function notificationBell() {
        return {
            open: false,
            items: [],
            unreadCount: 0,
            loading: false,
            csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            init() {
                // Lazy: load count quickly
                this.refresh();
            },
            toggle() {
                this.open = !this.open;
                if (this.open) {
                    this.refresh();
                }
            },
            async refresh() {
                if (this.loading) return;
                this.loading = true;
                try {
                    const res = await fetch('{{ route('notifications.index') }}', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        }
                    });
                    const data = await res.json();
                    this.items = data.items || [];
                    this.unreadCount = data.unread_count || 0;
                } catch (e) {
                    console.error('Failed to load notifications', e);
                } finally {
                    this.loading = false;
                }
            },
            async markRead(n) {
                if (!n || n.read_at) return;
                // hover on desktop
                await this._markReadRequest(n);
            },
            async onItemClick(n) {
                // tap/click on mobile + also makes read
                if (!n) return;
                if (!n.read_at) {
                    await this._markReadRequest(n);
                }
                if (n.url) {
                    window.location.href = n.url;
                }
            },
            async _markReadRequest(n) {
                try {
                    const res = await fetch(`/notifications/${n.id}/read`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        body: JSON.stringify({}),
                    });
                    const data = await res.json();
                    n.read_at = new Date().toISOString();
                    this.unreadCount = data.unread_count ?? Math.max(0, this.unreadCount - 1);
                } catch (e) {
                    console.error('Failed to mark notification read', e);
                }
            }
        }
    }
</script>
