<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Life Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-slate-900 mb-2">Life Dashboard</h1>
            <p class="text-slate-600">Войдите в свой аккаунт</p>
        </div>

        @if ($errors->any())
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl">
                <ul class="text-sm text-red-600 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-6">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-2">Email</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       value="{{ old('email') }}" 
                       required 
                       autofocus
                       class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-2">Пароль</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required
                       class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
            </div>

            <div class="flex items-center">
                <input type="checkbox" 
                       id="remember" 
                       name="remember" 
                       class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                <label for="remember" class="ml-2 text-sm text-slate-600">Запомнить меня</label>
            </div>

            <button type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-xl transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                Войти
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-slate-600">
                Нет аккаунта? 
                <a href="{{ route('register') }}" class="text-blue-600 hover:text-blue-700 font-medium">Зарегистрироваться</a>
            </p>
        </div>
    </div>
</body>
</html>
