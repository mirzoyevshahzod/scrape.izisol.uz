{{--
    Har bir sahifaning <head> qismiga qo'shiladi:
    1) Barcha same-origin POST/PUT/DELETE fetch so'rovlariga CSRF token qo'shadi
       (/api/* endpoint'lar endi session + CSRF bilan himoyalangan).
    2) Sessiya tugagan bo'lsa (401/419 yoki /login'ga redirect), login sahifasiga o'tkazadi.
    3) O'ng yuqori burchakda foydalanuvchi nomi va "Chiqish" tugmasini ko'rsatadi.
--}}
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    (function () {
        const token = @json(csrf_token());
        const loginUrl = @json(route('login'));
        const originalFetch = window.fetch;

        window.fetch = function (input, init) {
            init = init || {};
            const isRequest = input instanceof Request;
            const method = (init.method || (isRequest ? input.method : 'GET')).toUpperCase();
            const url = new URL(isRequest ? input.url : input, window.location.href);

            if (method !== 'GET' && method !== 'HEAD' && url.origin === window.location.origin) {
                const headers = new Headers(init.headers || (isRequest ? input.headers : undefined));

                if (!headers.has('X-CSRF-TOKEN')) {
                    headers.set('X-CSRF-TOKEN', token);
                }

                init.headers = headers;
            }

            return originalFetch.call(this, input, init).then(function (response) {
                const redirectedToLogin = response.redirected && new URL(response.url).pathname === new URL(loginUrl).pathname;

                if (response.status === 401 || response.status === 419 || redirectedToLogin) {
                    window.location.href = loginUrl;
                }

                return response;
            });
        };
    })();
</script>
@auth
    <style>
        .auth-chip {
            position: fixed;
            top: 12px;
            right: 12px;
            z-index: 1080;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 8px 6px 14px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 999px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
            font-size: 14px;
        }
        .auth-chip form { margin: 0; }
        .auth-chip button {
            border: none;
            border-radius: 999px;
            padding: 4px 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            font-size: 13px;
        }
    </style>
    <template id="auth-chip-template">
        <div class="auth-chip">
            <span>{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Chiqish</button>
            </form>
        </div>
    </template>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.body.appendChild(document.getElementById('auth-chip-template').content.cloneNode(true));
        });
    </script>
@endauth
