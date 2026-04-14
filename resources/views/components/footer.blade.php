<footer class="site-footer text-slate-200">
    <div class="site-footer__shell">
        <div class="site-footer__panel">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-cyan-300/10 bg-white/5 p-5 text-center md:text-left">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-400">Copyright</p>
                    <p class="mt-3 text-sm text-slate-200">&copy; {{ date('Y') }} Games Outbreak. {{ __('All rights reserved.') }}</p>
                </div>

                <div class="rounded-2xl border border-cyan-300/10 bg-white/5 p-5 text-center md:text-left">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-400">Powered By</p>
                    <p class="mt-3 text-sm leading-6 text-slate-200">
                        <a href="https://www.igdb.com" target="_blank" rel="noopener noreferrer" class="text-orange-300 transition hover:text-orange-200">IGDB</a>
                        {{ __('and') }}
                        <a href="https://steamspy.com/" target="_blank" rel="noopener noreferrer" class="text-orange-300 transition hover:text-orange-200">steamspy</a>
                    </p>
                </div>

                <div class="rounded-2xl border border-cyan-300/10 bg-white/5 p-5 text-center md:text-left">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-400">Credits</p>
                    <p class="mt-3 text-sm leading-6 text-slate-200">
                        {{ __('Made with') }} <span class="text-orange-300">{{ __('love') }}</span> {{ __('by') }}
                        <span class="text-orange-300">Cláudio Varandas</span>
                        {{ __('using open source technologies.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</footer>
