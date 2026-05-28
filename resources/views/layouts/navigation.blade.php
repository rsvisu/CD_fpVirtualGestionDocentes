<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <img src="{{ asset('img/fpvirtual_logo.png') }}" alt="Mi Logo" style="width: 50px;">
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Panel Usuario') }}
                    </x-nav-link>
                </div>
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('alta_docente')" :active="request()->routeIs('alta_docente')">
                        {{ __('Alta Docente') }}
                    </x-nav-link>
                </div>
                <!-- Añado esto ya que he comentado el dropdown -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('establecer_docencia.index')" :active="request()->routeIs('establecer_docencia.index')">
                        {{ __('Establecer docencia') }}
                    </x-nav-link>
                </div>
                <!-- Dropdown Establecer -->
                <!--<div class="hidden sm:flex sm:items-center sm:-my-px sm:ms-10">
                    <x-dropdown align="left" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                <span>{{ __('Establecer...') }}</span>
                                <svg class="ms-1 h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('establecer_coordinador.index')">
                                {{ __('Coordinador/es') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('establecer_tutor.index')">
                                {{ __('Tutor/es') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('establecer_docencia.index')">
                                {{ __('Docencia') }}
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>
                </div>-->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('docentes.index')" :active="request()->routeIs('docentes.index')">
                        {{ __('Baja Docente') }}
                    </x-nav-link>
                </div>
                @if(auth()->check() && auth()->user()->is_admin)
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('admin.alta-plataforma')" :active="request()->routeIs('admin.alta-plataforma')">
                        {{ __('Alta Plataforma') }}
                    </x-nav-link>
                </div>
                @endif
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->nombre }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        

                        <!-- Authentication -->
                        <x-dropdown-link :href="route('profile.edit')" 
                                    class="px-4 py-3 hover:bg-blue-50 text-gray-700 transition-all duration-300 border-b border-gray-100 flex items-center space-x-2 group">
                                    <span class="p-1.5 bg-blue-100 rounded-lg text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-colors duration-300">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                    </span>
                                    <span class="group-hover:text-blue-600 transition-colors duration-300">{{ __('Perfil') }}</span>
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">

                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();" 
                                class="px-4 py-3 hover:bg-blue-50 text-gray-700 transition-all duration-300 flex items-center space-x-2 group">
                                <span class="p-1.5 bg-red-100 rounded-lg text-red-600 group-hover:bg-red-600 group-hover:text-white transition-colors duration-300">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                </span>
                                <span class="group-hover:text-red-600 transition-colors duration-300">{{ __('Cerrar sesión') }}</span>
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
                {{ __('Panel Usuario') }}
            </x-responsive-nav-link>
        </div>
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('alta_docente')" :active="request()->routeIs('alta_docente')">
                {{ __('Alta Docente') }}
            </x-responsive-nav-link>
        </div>
        <!-- Responsive Dropdown Establecer -->
        <div class="pt-2 pb-3 space-y-1">
            <div x-data="{ open: false }">
                <button @click="open = !open" class="w-full flex justify-between items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 focus:outline-none">
                    <span>{{ __('Establecer') }}</span>
                    <svg :class="{ 'transform rotate-180': open }" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div x-show="open" class="space-y-1 pl-4">
                    <x-responsive-nav-link :href="route('establecer_coordinador.index')">
                        {{ __('Coordinador/es') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('establecer_tutor.index')">
                        {{ __('Tutor/es') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('establecer_docencia.index')">
                        {{ __('Docencia') }}
                    </x-responsive-nav-link>
                </div>
            </div>
        </div>
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('docentes.index')" :active="request()->routeIs('docentes.index')">
                {{ __('Baja Docente') }}
            </x-responsive-nav-link>
        </div>
        @if(auth()->check() && auth()->user()->is_admin)
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('admin.alta-plataforma')" :active="request()->routeIs('admin.alta-plataforma')">
                {{ __('Alta Plataforma') }}
            </x-responsive-nav-link>
        </div>
        @endif
        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="mt-3 space-y-1">               

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Cerrar sesión') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
