</main>
    <footer class="bg-[#E0F2F7] border-t border-[#ABD0D8]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6"> 
            <div class="flex flex-col md:flex-row justify-between items-start gap-6"> 
                
                <!-- Logo & Address -->
                <div class="space-y-2"> 
                    <a href="<?php echo $dashboard_url; ?>" class="inline-flex items-center gap-2 mb-1 group"> 
                        <img src="/cakrawala-connect/assets/images/Logo Universitas Cakrawala1.png" 
                            alt="Logo Universitas Cakrawala" 
                            class="h-8 group-hover:opacity-80 transition-opacity"> 
                        <div class="flex flex-col leading-tight">
                            <span class="font-bold text-[#2A8FA9] text-base group-hover:opacity-80 transition-opacity">Cakrawala</span> 
                            <span class="font-bold text-[#2A8FA9] text-base group-hover:opacity-80 transition-opacity">Connect</span> 
                        </div>
                    </a>
                    <p class="text-xs text-[#495057] max-w-xs leading-relaxed"> 
                        Jl. Kemang Timur No.1, RT.14/RW.8, Pejaten Bar., Ps. Minggu, 
                        Kota Jakarta Selatan, DKI Jakarta 12510
                    </p>
                </div>

                <!-- Quick Links -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 mt-5"> 
                    <div>
                        <a href="/cakrawala-connect/about.php" 
                        class="inline-flex items-center text-[#2A8FA9] hover:text-[#409BB2] font-medium text-xs group transition-all duration-200"> 
                            Tentang Platform
                            <span class="iconify ml-1 transition-transform duration-200 group-hover:translate-x-1" 
                                data-icon="mdi:arrow-right" data-width="14"></span> 
                        </a>
                    </div>
                    <div>
                        <a href="/cakrawala-connect/for-partners.php" 
                        class="inline-flex items-center text-[#2A8FA9] hover:text-[#409BB2] font-medium text-xs group transition-all duration-200">
                            Untuk Mitra Industri
                            <span class="iconify ml-1 transition-transform duration-200 group-hover:translate-x-1" 
                                data-icon="mdi:arrow-right" data-width="14"></span>
                        </a>
                    </div>
                    <div>
                        <a href="/cakrawala-connect/" 
                        class="inline-flex items-center text-[#2A8FA9] hover:text-[#409BB2] font-medium text-xs group transition-all duration-200">
                            Career Center (CDC)
                            <span class="iconify ml-1 transition-transform duration-200 group-hover:translate-x-1" 
                                data-icon="mdi:arrow-right" data-width="14"></span>
                        </a>
                    </div>
                    <div>
                        <a href="/cakrawala-connect/privacy-terms.php" 
                        class="inline-flex items-center text-[#2A8FA9] hover:text-[#409BB2] font-medium text-xs group transition-all duration-200">
                            Kebijakan Privasi
                            <span class="iconify ml-1 transition-transform duration-200 group-hover:translate-x-1" 
                                data-icon="mdi:arrow-right" data-width="14"></span>
                        </a>
                    </div>
                </div>

            </div>

            <!-- Bottom Section -->
            <div class="flex flex-col md:flex-row justify-between items-center text-xs text-[#495057] border-t border-[#ABD0D8] mt-6 pt-4"> <!-- text-sm jadi text-xs, mt-8 jadi mt-6, pt-6 jadi pt-4 -->
                <span>© <?php echo date('Y'); ?> Cakrawala Connect - Universitas Cakrawala. All Rights Reserved.</span>
                <div class="flex space-x-3 mt-3 md:mt-0"> 
                    <a href="https://www.tiktok.com/@cakrawalauniversity" 
                    class="text-[#495057] hover:text-[#2A8FA9] transition-colors p-1 rounded-full hover:bg-white/50"> 
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"> 
                            <path d="M19.589 6.686a4.793 4.793 0 0 1-3.77-4.245V2h-3.445v13.672a2.896 2.896 0 0 1-5.201 1.743l-.002-.001.002.001a2.895 2.895 0 0 1 3.183-4.51v-3.5a6.329 6.329 0 0 0-5.394 10.692 6.33 6.33 0 0 0 10.857-4.424V8.687a8.182 8.182 0 0 0 4.773 1.526V6.79a4.831 4.831 0 0 1-1.003-.104z"/>
                        </svg>
                    </a>
                    <a href="https://x.com/CakrawalaUniv" 
                    class="text-[#495057] hover:text-[#2A8FA9] transition-colors p-1 rounded-full hover:bg-white/50">
                        <span class="iconify" data-icon="mdi:twitter" data-width="16"></span> 
                    </a>
                    <a href="https://www.instagram.com/cdccakrawala/" 
                    class="text-[#495057] hover:text-[#2A8FA9] transition-colors p-1 rounded-full hover:bg-white/50">
                        <span class="iconify" data-icon="mdi:instagram" data-width="16"></span> 
                    </a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>