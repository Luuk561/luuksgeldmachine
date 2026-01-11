<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Affiliate Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#1a1d2e] min-h-screen flex items-center justify-center font-sans antialiased">

    <div class="w-full max-w-md px-6">
        <!-- Logo/Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-light text-slate-100 mb-2">Affiliate Dashboard</h1>
            <p class="text-slate-400">Log in om verder te gaan</p>
        </div>

        <!-- Login Card -->
        <div class="bg-[#252839] backdrop-blur-light rounded-xl p-8 shadow-lg border border-slate-700/20">

            <!-- Session Status -->
            @if (session('status'))
                <div class="mb-4 text-sm text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 rounded-lg p-3">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <!-- Email Address -->
                <div class="mb-6">
                    <label for="email" class="block text-sm font-medium text-slate-300 mb-2">Email</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        class="w-full px-4 py-3 bg-[#1a1d2e] text-slate-100 border border-slate-700/50 rounded-lg focus:outline-none focus:border-lavender/50 transition-colors"
                        placeholder="jouw@email.nl"
                    />
                    @error('email')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password -->
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-slate-300 mb-2">Wachtwoord</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="w-full px-4 py-3 bg-[#1a1d2e] text-slate-100 border border-slate-700/50 rounded-lg focus:outline-none focus:border-lavender/50 transition-colors"
                        placeholder="••••••••"
                    />
                    @error('password')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Remember Me -->
                <div class="flex items-center mb-6">
                    <input
                        id="remember_me"
                        type="checkbox"
                        name="remember"
                        class="w-4 h-4 bg-[#1a1d2e] border-slate-700/50 rounded text-lavender focus:ring-lavender/50 focus:ring-offset-0"
                    />
                    <label for="remember_me" class="ml-2 text-sm text-slate-400">Onthoud mij</label>
                </div>

                <div class="flex items-center justify-between">
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-sm text-slate-400 hover:text-lavender transition-colors">
                            Wachtwoord vergeten?
                        </a>
                    @endif

                    <button
                        type="submit"
                        class="px-6 py-3 bg-lavender text-white rounded-lg font-medium hover:bg-lavender/90 transition-all duration-200 shadow-lg"
                    >
                        Inloggen
                    </button>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6">
            <p class="text-xs text-slate-500">© {{ date('Y') }} luuksgeldmachine.nl</p>
        </div>
    </div>

</body>
</html>
