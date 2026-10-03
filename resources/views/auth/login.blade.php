<x-layouts.app title="Iniciar sesion | MiCatalogo">
    <style>
        :root {
            --login-navy: #071B35;
            --login-blue: #008CFF;
            --login-blue-strong: #0568F5;
            --login-blue-light: #49B5FF;
            --login-background: #F5F7FA;
            --login-text-secondary: #718096;
            --login-border: #DCE6F2;
        }

        .login-page {
            align-items: center;
            background: linear-gradient(145deg, #071B35 0%, #0B3263 34%, #008CFF 100%);
            display: flex;
            justify-content: center;
            min-height: 100vh;
            overflow: hidden;
            padding: 48px 24px;
            position: relative;
        }

        .login-shape { pointer-events: none; position: absolute; }
        .login-shape-a { background: linear-gradient(135deg, #FFFFFF, #BEE4FF); bottom: -6vh; clip-path: polygon(0 0, 100% 70%, 72% 100%, 0 100%); height: 48vh; left: -18vw; width: 60vw; }
        .login-shape-b { background: linear-gradient(140deg, #008CFF, #1675FF); clip-path: polygon(42% 0, 100% 0, 100% 75%, 0 100%); height: 55vh; right: -13vw; top: -4vh; width: 54vw; }
        .login-shape-c { background: linear-gradient(135deg, #E7F5FF, #62BCFF); bottom: -10vh; clip-path: polygon(35% 0, 100% 36%, 100% 100%, 0 100%); height: 42vh; right: -9vw; width: 48vw; }
        .login-shape-d { background: rgba(0, 140, 255, .52); clip-path: polygon(0 0, 100% 50%, 45% 100%, 0 70%); height: 33vh; left: -6vw; top: 28vh; width: 34vw; }

        .login-wrapper { position: relative; width: min(650px, 100%); z-index: 1; }
        .login-brand { align-items: center; color: white; display: flex; justify-content: center; margin-bottom: 30px; text-decoration: none; }
        .login-brand-mark { height: 56px; margin-right: 14px; position: relative; width: 56px; }
        .login-brand-mark::before, .login-brand-mark::after { border-radius: 5px; content: ''; height: 46px; position: absolute; transform: skewY(-22deg); width: 30px; }
        .login-brand-mark::before { background: linear-gradient(180deg, #21C1FF, #008CFF); left: 4px; top: 5px; }
        .login-brand-mark::after { background: linear-gradient(180deg, #1675FF, #005AE0); right: 4px; top: 1px; }
        .login-brand-name { font-size: 42px; font-weight: 800; letter-spacing: -1.4px; line-height: 1; }
        .login-brand-name span { color: var(--login-blue-light); }

        .login-card { background: white; border: 1px solid rgba(255, 255, 255, .35); border-radius: 28px; box-shadow: 0 30px 70px rgba(7, 27, 53, .2); overflow: hidden; }
        .login-card-header { align-items: center; background: linear-gradient(145deg, #071B35, #075CC6 58%, #008CFF); color: white; display: flex; flex-direction: column; min-height: 190px; overflow: hidden; padding: 48px 24px 42px; position: relative; text-align: center; }
        .login-card-header::before { background: rgba(45, 173, 255, .46); bottom: -135px; content: ''; height: 250px; position: absolute; right: -60px; transform: rotate(38deg); width: 260px; }
        .login-card-header::after { background: rgba(0, 140, 255, .48); bottom: -100px; content: ''; height: 150px; left: -130px; position: absolute; transform: rotate(25deg); width: 340px; }
        .login-card-header h1, .login-card-header p { position: relative; z-index: 1; }
        .login-card-header h1 { font-size: 42px; font-weight: 800; letter-spacing: -.8px; line-height: 1.1; margin: 0; }
        .login-card-header p { color: rgba(255, 255, 255, .78); font-size: 18px; margin: 12px 0 0; }
        .login-body { padding: 42px 48px 34px; }
        .login-field { margin-bottom: 20px; position: relative; }
        .login-field input { background: #FBFCFE; border: 1px solid var(--login-border); border-radius: 16px; color: var(--login-navy); font-size: 16px; height: 64px; outline: none; padding: 0 58px; transition: border-color .2s ease, box-shadow .2s ease, background .2s ease; width: 100%; }
        .login-field input:focus { background: white; border-color: var(--login-blue); box-shadow: 0 0 0 4px rgba(0, 140, 255, .12); }
        .login-field-icon { color: #7B8DA6; height: 23px; left: 20px; position: absolute; top: 50%; transform: translateY(-50%); width: 23px; }
        .login-password-toggle { align-items: center; background: transparent; border: 0; color: var(--login-text-secondary); cursor: pointer; display: flex; height: 44px; justify-content: center; position: absolute; right: 10px; top: 10px; width: 44px; }
        .login-options { align-items: center; display: flex; gap: 18px; justify-content: space-between; margin: 6px 0 28px; }
        .login-remember { align-items: center; color: var(--login-navy); cursor: pointer; display: flex; font-size: 14px; gap: 9px; }
        .login-remember input { accent-color: var(--login-blue); height: 19px; width: 19px; }
        .login-link { color: var(--login-blue-strong); font-size: 14px; font-weight: 700; text-decoration: none; }
        .login-link:hover { text-decoration: underline; }
        .login-submit { align-items: center; background: linear-gradient(100deg, #055EDD, #008CFF); border: 0; border-radius: 16px; box-shadow: 0 13px 27px rgba(0, 108, 255, .23); color: white; cursor: pointer; display: flex; font-size: 17px; font-weight: 700; gap: 15px; height: 64px; justify-content: center; transition: transform .2s ease, box-shadow .2s ease; width: 100%; }
        .login-submit:hover { box-shadow: 0 17px 34px rgba(0, 108, 255, .29); transform: translateY(-2px); }
        .login-activation { color: var(--login-text-secondary); font-size: 14px; text-align: center; }
        .login-register { color: var(--login-text-secondary); font-size: 13px; margin-top: 12px; text-align: center; }
        .login-secure { align-items: center; background: #F5F8FC; border-radius: 13px; color: #73839B; display: flex; font-size: 13px; font-weight: 500; gap: 8px; justify-content: center; margin-top: 30px; padding: 15px; }
        .login-alert { border-radius: 12px; font-size: 14px; margin-bottom: 18px; padding: 13px 15px; }
        .login-alert-error { background: #FFF0F2; border: 1px solid #FFD0D6; color: #B82237; }
        .login-alert-success { background: #ECFDF5; border: 1px solid #A7F3D0; color: #047857; }
        .login-field-error { color: #B82237; display: block; font-size: 13px; margin-top: 6px; }

        @media (max-width: 640px) {
            .login-page { padding: 28px 16px; }
            .login-brand { margin-bottom: 22px; }
            .login-brand-name { font-size: 33px; }
            .login-brand-mark { height: 45px; width: 45px; }
            .login-brand-mark::before, .login-brand-mark::after { height: 38px; width: 24px; }
            .login-card-header { min-height: 170px; padding: 38px 20px 32px; }
            .login-card-header h1 { font-size: 32px; }
            .login-card-header p { font-size: 15px; }
            .login-body { padding: 32px 20px 24px; }
            .login-field input, .login-submit { height: 58px; }
            .login-options { align-items: flex-start; flex-direction: column; }
            .login-shape-a { width: 100vw; }
            .login-shape-b { width: 85vw; }
        }
    </style>

    <main class="login-page">
        <div class="login-shape login-shape-a"></div>
        <div class="login-shape login-shape-b"></div>
        <div class="login-shape login-shape-c"></div>
        <div class="login-shape login-shape-d"></div>

        <div class="login-wrapper">
            <a class="login-brand" href="{{ route('home') }}" aria-label="Ir al inicio de MiCatalogo">
                <span class="login-brand-mark" aria-hidden="true"></span>
                <span class="login-brand-name">Mi<span>Catalogo</span></span>
            </a>

            <section class="login-card" aria-labelledby="login-title">
                <header class="login-card-header">
                    <h1 id="login-title">Mi Cuenta</h1>
                    <p>Accede a tu panel</p>
                </header>

                <div class="login-body">
                    @if (session('status'))
                        <div class="login-alert login-alert-success">{{ session('status') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="login-alert login-alert-error">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ url('/login') }}" x-data="{ showPassword: false }">
                        @csrf

                        <div class="login-field">
                            <svg class="login-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="Correo electronico" autocomplete="email" required autofocus>
                            @error('email') <span class="login-field-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="login-field">
                            <svg class="login-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                            <input id="password" name="password" :type="showPassword ? 'text' : 'password'" placeholder="Contrasena" autocomplete="current-password" required>
                            <button class="login-password-toggle" type="button" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Ocultar contrasena' : 'Mostrar contrasena'">
                                <svg x-show="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="23" height="23"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                <svg x-show="showPassword" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="23" height="23"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.1A10.7 10.7 0 0 1 12 5c6 0 9.5 7 9.5 7a18.5 18.5 0 0 1-3.2 4.1M6.2 6.2C3.8 8 2.5 12 2.5 12s3.5 7 9.5 7c1 0 2-.2 2.9-.5"/></svg>
                            </button>
                            @error('password') <span class="login-field-error">{{ $message }}</span> @enderror
                        </div>

                        @if (config('services.turnstile.enabled'))
                            <div class="mb-5 flex justify-center rounded-2xl border border-[#DCE6F2] bg-[#F5F8FC] p-3">
                                <div class="cf-turnstile" data-action="login" data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
                            </div>
                            @error('cf-turnstile-response') <span class="login-field-error">{{ $message }}</span> @enderror
                        @endif

                        <div class="login-options">
                            <label class="login-remember">
                                <input name="remember" type="checkbox" @checked(old('remember'))>
                                <span>Recordarme en este dispositivo</span>
                            </label>
                            <a class="login-link" href="{{ url('/forgot-password') }}">Olvidaste tu contrasena?</a>
                        </div>

                        <button class="login-submit" type="submit">Iniciar sesion <span aria-hidden="true">&rarr;</span></button>
                    </form>

                    <p class="login-activation">Tienes un codigo de activacion? <a class="login-link" href="{{ route('verification.notice') }}">Activalo aqui</a></p>
                    <p class="login-register">Aun no tienes tu catalogo? <a class="login-link" href="{{ url('/register') }}">Crea tu tienda</a></p>
                    <div class="login-secure">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 3 8.2 7 10 4-1.8 7-5.5 7-10V6l-7-3Z"/><rect x="9" y="10" width="6" height="5" rx="1"/><path d="M10 10V8a2 2 0 0 1 4 0v2"/></svg>
                        Acceso seguro cifrado
                    </div>
                </div>
            </section>
        </div>
    </main>

    @if (config('services.turnstile.enabled'))
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
</x-layouts.app>
