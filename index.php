<?php
include 'includes/config.php';
include 'includes/functions.php';

// Redirect jika sudah login
if (isLoggedIn()) {
    $role = getUserRole();
    $role_folder_map = [
        'mitra_industri' => 'mitra-industri',
        'student' => 'student', 
        'admin' => 'admin'
    ];
    $folder_name = $role_folder_map[$role] ?? $role;
    header("Location: /cakrawala-connect/dashboard/{$folder_name}/index.php");
    exit();
}
?>

<?php include 'includes/header.php'; ?>

<style>
    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(5deg); }
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-50px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(50px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes particle-float {
        0%, 100% { transform: translate(0, 0) scale(1); }
        25% { transform: translate(10px, -10px) scale(1.1); }
        50% { transform: translate(-5px, -20px) scale(0.9); }
        75% { transform: translate(-10px, -10px) scale(1.05); }
    }
    
    @keyframes spin-slow {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    @keyframes pulse-glow {
        0%, 100% { opacity: 0.5; transform: scale(1); }
        50% { opacity: 0.8; transform: scale(1.1); }
    }
    
    .animate-float {
        animation: float 3s ease-in-out infinite;
    }
    
    .animate-fade-in-up {
        animation: fadeInUp 0.8s ease-out forwards;
    }
    
    .animate-slide-in-left {
        animation: slideInLeft 0.8s ease-out forwards;
    }
    
    .animate-slide-in-right {
        animation: slideInRight 0.8s ease-out forwards;
    }
    
    .gradient-text {
        background: linear-gradient(135deg, #2A8FA9 0%, #F9A825 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .feature-card {
        transition: all 0.3s ease;
    }
    
    .feature-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }
    
    .scroll-reveal {
        opacity: 0;
        transform: translateY(50px);
        transition: all 0.6s ease-out;
    }
    
    .scroll-reveal.active {
        opacity: 1;
        transform: translateY(0);
    }
    
    /* Particle Container */
    .particle-container {
        position: absolute;
        width: 100%;
        height: 100%;
        overflow: hidden;
    }
    
    .particle {
        position: absolute;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        pointer-events: none;
    }
    
    /* Animated shapes */
    .shape {
        position: absolute;
        opacity: 0.1;
    }
    
    .shape-circle {
        border-radius: 50%;
        border: 3px solid white;
    }
    
    .shape-square {
        border: 3px solid white;
        transform: rotate(45deg);
    }
    
    .shape-triangle {
        width: 0;
        height: 0;
        border-left: 30px solid transparent;
        border-right: 30px solid transparent;
        border-bottom: 50px solid rgba(255, 255, 255, 0.2);
    }
    
    /* Decorative pattern */
    .pattern-dots {
        background-image: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
        background-size: 20px 20px;
    }
    
    .mesh-gradient {
        background: 
            radial-gradient(at 0% 0%, rgba(42, 143, 169, 0.3) 0px, transparent 50%),
            radial-gradient(at 100% 0%, rgba(249, 168, 37, 0.2) 0px, transparent 50%),
            radial-gradient(at 100% 100%, rgba(42, 143, 169, 0.3) 0px, transparent 50%),
            radial-gradient(at 0% 100%, rgba(249, 168, 37, 0.2) 0px, transparent 50%);
    }
</style>

<!-- Hero Section with Particles -->
<section class="relative bg-gradient-to-br from-[#2A8FA9] via-[#409BB2] to-[#2A8FA9] text-white py-20 overflow-hidden">
    <!-- Particle Container -->
    <div class="particle-container" id="particles"></div>
    
    <!-- Mesh Gradient Overlay -->
    <div class="absolute inset-0 mesh-gradient"></div>
    
    <!-- Pattern Overlay -->
    <div class="absolute inset-0 pattern-dots"></div>
    
    <!-- Animated Shapes -->
    <div class="shape shape-circle w-32 h-32 top-20 left-10 animate-float" style="animation-duration: 6s;"></div>
    <div class="shape shape-square w-24 h-24 top-40 right-20 animate-float" style="animation-duration: 8s; animation-delay: 1s;"></div>
    <div class="shape shape-circle w-40 h-40 bottom-20 left-1/4 animate-float" style="animation-duration: 7s; animation-delay: 2s;"></div>
    <div class="shape shape-triangle top-1/2 right-10 animate-float" style="animation-duration: 9s;"></div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div class="space-y-6 animate-slide-in-left">
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold leading-tight">
                    Hubungkan Potensi Mahasiswa Cakrawala dengan 
                    <span class="text-[#F9A825]">Peluang Karir Nyata</span>
                </h1>
                <p class="text-xl text-blue-100 leading-relaxed">
                    Platform portofolio berbasis bukti yang menjembatani talenta mahasiswa Universitas Cakrawala dengan kebutuhan industri profesional.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 pt-4">
                    <a href="/cakrawala-connect/register.php" 
                       class="group bg-[#F9A825] hover:bg-[#F57F17] text-white font-bold py-4 px-8 rounded-lg text-lg transition-all duration-300 transform hover:scale-105 shadow-lg text-center relative overflow-hidden">
                        <span class="relative z-10">Daftar Sekarang</span>
                        <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-20 transition-opacity"></div>
                    </a>
                    <a href="/cakrawala-connect/login.php" 
                       class="group bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white font-bold py-4 px-8 rounded-lg text-lg transition-all duration-300 border border-white/30 text-center">
                        <span class="relative z-10">Login</span>
                    </a>
                </div>
            </div>
            <div class="flex justify-center animate-slide-in-right">
                <div class="relative w-full max-w-md">
                    <!-- Main Illustration Card -->
                    <div class="relative bg-white/10 backdrop-blur-md rounded-3xl p-8 border border-white/20 shadow-2xl">
                        <!-- Connection Visualization -->
                        <div class="relative h-80">
                            <!-- Students Side -->
                            <div class="absolute left-0 top-1/2 transform -translate-y-1/2">
                                <div class="relative">
                                    <!-- Student utama - TANPA animasi pulse -->
                                    <div class="w-20 h-20 bg-[#F9A825] rounded-full flex items-center justify-center shadow-lg">
                                        <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                                        </svg>
                                    </div>
                                    <div class="absolute -top-2 -right-2 w-6 h-6 bg-green-400 rounded-full border-2 border-white"></div>
                                </div>
                                <!-- Multiple students indicators - MASIH ada animasi -->
                                <div class="absolute -bottom-3 -left-3 w-12 h-12 bg-[#F9A825]/70 rounded-full flex items-center justify-center animate-pulse" style="animation-delay: 0.5s;">
                                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                                    </svg>
                                </div>
                                <div class="absolute -top-3 left-10 w-12 h-12 bg-[#F9A825]/70 rounded-full flex items-center justify-center animate-pulse" style="animation-delay: 1s;">
                                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                                    </svg>
                                </div>
                            </div>
                            
                            <!-- Connection Lines with Animation -->
                            <svg class="absolute inset-0 w-full h-full" style="z-index: 1;">
                                <defs>
                                    <linearGradient id="lineGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" style="stop-color:#F9A825;stop-opacity:1" />
                                        <stop offset="100%" style="stop-color:#fff;stop-opacity:1" />
                                    </linearGradient>
                                </defs>
                                <!-- Animated connection lines -->
                                <path d="M 80 160 Q 200 120, 320 160" stroke="url(#lineGradient)" stroke-width="3" fill="none" stroke-dasharray="5,5">
                                    <animate attributeName="stroke-dashoffset" from="0" to="-10" dur="0.5s" repeatCount="indefinite"/>
                                </path>
                                <path d="M 80 160 Q 200 160, 320 160" stroke="url(#lineGradient)" stroke-width="3" fill="none" stroke-dasharray="5,5">
                                    <animate attributeName="stroke-dashoffset" from="0" to="-10" dur="0.5s" repeatCount="indefinite"/>
                                </path>
                                <path d="M 80 160 Q 200 200, 320 160" stroke="url(#lineGradient)" stroke-width="3" fill="none" stroke-dasharray="5,5">
                                    <animate attributeName="stroke-dashoffset" from="0" to="-10" dur="0.5s" repeatCount="indefinite"/>
                                </path>
                            </svg>
                            
                            <!-- Industry Side -->
                            <div class="absolute right-0 top-1/2 transform -translate-y-1/2">
                                <div class="relative">
                                    <div class="w-24 h-24 bg-white rounded-2xl flex items-center justify-center shadow-xl">
                                        <svg class="w-14 h-14 text-[#2A8FA9]" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <!-- Industry badge -->
                                    <div class="absolute -top-2 -right-2 bg-blue-500 text-white text-xs px-2 py-1 rounded-full font-bold shadow">50+</div>
                                </div>
                            </div>
                            
                            <!-- Floating Icons -->
                            <div class="absolute top-10 left-1/2 transform -translate-x-1/2 w-12 h-12 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center animate-float" style="animation-delay: 0.5s;">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            
                            <div class="absolute bottom-10 left-1/2 transform -translate-x-1/2 w-12 h-12 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center animate-float" style="animation-delay: 1s;">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Decorative Elements -->
                    <div class="absolute -top-6 -right-6 w-32 h-32 bg-[#F9A825] rounded-full opacity-20 blur-2xl animate-pulse"></div>
                    <div class="absolute -bottom-6 -left-6 w-40 h-40 bg-white rounded-full opacity-10 blur-2xl animate-pulse" style="animation-delay: 1s;"></div>
                    
                    <!-- Floating Stats -->
                    <div class="absolute -left-8 top-20 bg-white rounded-lg shadow-xl p-3 animate-float">
                        <div class="text-2xl font-bold text-[#2A8FA9]">500+</div>
                        <div class="text-xs text-gray-600">Mahasiswa</div>
                    </div>
                    
                    <div class="absolute -right-8 bottom-20 bg-white rounded-lg shadow-xl p-3 animate-float" style="animation-delay: 1s;">
                        <div class="text-2xl font-bold text-[#F9A825]">50+</div>
                        <div class="text-xs text-gray-600">Industri</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="py-16 bg-white relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-r from-blue-50 to-amber-50 opacity-50"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center scroll-reveal feature-card bg-gradient-to-br from-[#E0F2F7] to-white p-8 rounded-xl border-2 border-[#2A8FA9]/10">
                <div class="inline-block p-4 bg-[#2A8FA9]/10 rounded-full mb-4">
                    <svg class="w-12 h-12 text-[#2A8FA9]" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                    </svg>
                </div>
                <div class="text-5xl font-bold gradient-text mb-2">500+</div>
                <div class="text-gray-600 font-semibold">Mahasiswa Terdaftar</div>
            </div>
            <div class="text-center scroll-reveal feature-card bg-gradient-to-br from-[#FFF8E1] to-white p-8 rounded-xl border-2 border-[#F9A825]/10" style="transition-delay: 0.1s;">
                <div class="inline-block p-4 bg-[#F9A825]/10 rounded-full mb-4">
                    <svg class="w-12 h-12 text-[#F9A825]" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="text-5xl font-bold gradient-text mb-2">50+</div>
                <div class="text-gray-600 font-semibold">Mitra Industri</div>
            </div>
            <div class="text-center scroll-reveal feature-card bg-gradient-to-br from-[#E8F5E9] to-white p-8 rounded-xl border-2 border-green-500/10" style="transition-delay: 0.2s;">
                <div class="inline-block p-4 bg-green-100 rounded-full mb-4">
                    <svg class="w-12 h-12 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/>
                    </svg>
                </div>
                <div class="text-5xl font-bold gradient-text mb-2">280+</div>
                <div class="text-gray-600 font-semibold">Mahasiswa Tersalurkan</div>
            </div>
        </div>
    </div>
</section>

<!-- Untuk Mahasiswa Section -->
<section class="py-20 bg-gradient-to-br from-white via-blue-50 to-white relative overflow-hidden">
    <!-- Background decoration -->
    <div class="absolute top-0 right-0 w-96 h-96 bg-[#2A8FA9] rounded-full opacity-5 blur-3xl"></div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div class="order-2 lg:order-1 scroll-reveal">
                <div class="relative">
                    <!-- Main Card -->
                    <div class="bg-gradient-to-br from-[#2A8FA9] to-[#409BB2] rounded-3xl p-12 transform hover:scale-105 transition-transform duration-300 shadow-2xl relative overflow-hidden">
                        <!-- Animated background pattern -->
                        <div class="absolute inset-0 opacity-10">
                            <div class="absolute top-0 left-0 w-32 h-32 border-4 border-white rounded-full animate-ping"></div>
                            <div class="absolute bottom-0 right-0 w-40 h-40 border-4 border-white rounded-full animate-ping" style="animation-delay: 1s;"></div>
                        </div>
                        
                        <!-- Portfolio Illustration -->
                        <div class="relative z-10 grid grid-cols-2 gap-6">
                            <!-- Portfolio cards -->
                            <div class="bg-white/20 backdrop-blur-sm rounded-xl p-6 animate-float">
                                <div class="w-12 h-12 bg-[#F9A825] rounded-lg mb-3 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                                    </svg>
                                </div>
                                <div class="h-2 bg-white/50 rounded mb-2"></div>
                                <div class="h-2 bg-white/30 rounded w-3/4"></div>
                            </div>
                            
                            <div class="bg-white/20 backdrop-blur-sm rounded-xl p-6 animate-float" style="animation-delay: 0.5s;">
                                <div class="w-12 h-12 bg-[#F9A825] rounded-lg mb-3 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                                    </svg>
                                </div>
                                <div class="h-2 bg-white/50 rounded mb-2"></div>
                                <div class="h-2 bg-white/30 rounded w-2/3"></div>
                            </div>
                            
                            <div class="bg-white/20 backdrop-blur-sm rounded-xl p-6 animate-float col-span-2" style="animation-delay: 1s;">
                                <div class="flex items-center gap-4 mb-3">
                                    <div class="w-12 h-12 bg-[#F9A825] rounded-lg flex items-center justify-center">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <div class="h-2 bg-white/50 rounded mb-2"></div>
                                        <div class="h-2 bg-white/30 rounded w-3/4"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Floating badge -->
                    <div class="absolute -top-4 -right-4 bg-[#F9A825] text-white px-6 py-3 rounded-full font-bold shadow-xl animate-bounce">
                        Verified ✓
                    </div>
                </div>
            </div>
            
            <div class="order-1 lg:order-2 space-y-6 scroll-reveal">
                <h2 class="text-3xl md:text-4xl font-bold text-[#2A8FA9]">
                    Bagi Mahasiswa: <span class="gradient-text">Buktikan Kemampuanmu!</span>
                </h2>
                <div class="space-y-4">
                    <div class="feature-card flex items-start gap-4 bg-white p-6 rounded-xl shadow-lg border-l-4 border-[#2A8FA9]">
                        <div class="bg-gradient-to-br from-[#2A8FA9] to-[#409BB2] text-white p-3 rounded-lg mt-1 flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-gray-800 mb-1">Bangun Portofolio Profesional</h3>
                            <p class="text-gray-600">Tunjukkan proyek nyata dan keterampilan yang relevan dengan industri</p>
                        </div>
                    </div>
                    <div class="feature-card flex items-start gap-4 bg-white p-6 rounded-xl shadow-lg border-l-4 border-[#2A8FA9]">
                        <div class="bg-gradient-to-br from-[#2A8FA9] to-[#409BB2] text-white p-3 rounded-lg mt-1 flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-gray-800 mb-1">Bukti Nyata Keterampilan</h3>
                            <p class="text-gray-600">Portofolio berbasis bukti yang diverifikasi oleh sistem dan CDC</p>
                        </div>
                    </div>
                    <div class="feature-card flex items-start gap-4 bg-white p-6 rounded-xl shadow-lg border-l-4 border-[#2A8FA9]">
                        <div class="bg-gradient-to-br from-[#2A8FA9] to-[#409BB2] text-white p-3 rounded-lg mt-1 flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-gray-800 mb-1">Tingkatkan Visibilitas ke Industri</h3>
                            <p class="text-gray-600">Ditemukan oleh mitra industri terkemuka yang mencari talenta</p>
                        </div>
                    </div>
                </div>
                <a href="/cakrawala-connect/register.php" 
                   class="inline-flex items-center gap-2 bg-gradient-to-r from-[#2A8FA9] to-[#409BB2] hover:from-[#409BB2] hover:to-[#2A8FA9] text-white font-bold py-4 px-8 rounded-lg transition-all duration-300 transform hover:scale-105 shadow-lg">
                    Mulai Bangun Portofolio
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Untuk Mitra Industri Section -->
<section class="py-20 bg-gradient-to-br from-amber-50 via-white to-amber-50 relative overflow-hidden">
    <!-- Background decoration -->
    <div class="absolute bottom-0 left-0 w-96 h-96 bg-[#F9A825] rounded-full opacity-5 blur-3xl"></div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div class="space-y-6 scroll-reveal">
                <h2 class="text-3xl md:text-4xl font-bold text-[#2A8FA9]">
                    Bagi Mitra Industri: <span class="gradient-text">Temukan Talenta Siap Kerja!</span>
                </h2>
                <div class="space-y-4">
                    <div class="feature-card flex items-start gap-4 bg-white p-6 rounded-xl shadow-lg border-l-4 border-[#F9A825]">
                        <div class="bg-gradient-to-br from-[#F9A825] to-[#F57F17] text-white p-3 rounded-lg mt-1 flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-gray-800 mb-1">Akses ke Mahasiswa Eligible</h3>
                            <p class="text-gray-600">Temukan talenta yang sudah diverifikasi kelayakannya oleh CDC</p>
                        </div>
                    </div>
                    <div class="feature-card flex items-start gap-4 bg-white p-6 rounded-xl shadow-lg border-l-4 border-[#F9A825]">
                        <div class="bg-gradient-to-br from-[#F9A825] to-[#F57F17] text-white p-3 rounded-lg mt-1 flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-gray-800 mb-1">Portofolio Terverifikasi</h3>
                            <p class="text-gray-600">Lihat bukti nyata keterampilan melalui proyek yang telah diselesaikan</p>
                        </div>
                    </div>
                    <div class="feature-card flex items-start gap-4 bg-white p-6 rounded-xl shadow-lg border-l-4 border-[#F9A825]">
                        <div class="bg-gradient-to-br from-[#F9A825] to-[#F57F17] text-white p-3 rounded-lg mt-1 flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-gray-800 mb-1">Rekrutmen Lebih Efisien</h3>
                            <p class="text-gray-600">Proses pencarian dan rekrutmen talenta yang lebih terarah</p>
                        </div>
                    </div>
                </div>
                <a href="/cakrawala-connect/register.php" 
                   class="inline-flex items-center gap-2 bg-gradient-to-r from-[#F9A825] to-[#F57F17] hover:from-[#F57F17] hover:to-[#F9A825] text-white font-bold py-4 px-8 rounded-lg transition-all duration-300 transform hover:scale-105 shadow-lg">
                    Daftar Sebagai Mitra Industri
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </a>
            </div>
            
            <div class="scroll-reveal">
                <div class="relative">
                    <!-- Main Card -->
                    <div class="bg-gradient-to-br from-[#F9A825] to-[#F57F17] rounded-3xl p-12 transform hover:scale-105 transition-transform duration-300 shadow-2xl relative overflow-hidden">
                        <!-- Animated background pattern -->
                        <div class="absolute inset-0 opacity-10">
                            <div class="absolute top-10 right-10 w-24 h-24 border-4 border-white rounded-lg animate-spin" style="animation-duration: 10s;"></div>
                            <div class="absolute bottom-10 left-10 w-32 h-32 border-4 border-white rounded-lg animate-spin" style="animation-duration: 15s; animation-direction: reverse;"></div>
                        </div>
                        
                        <!-- Industry Dashboard Illustration -->
                        <div class="relative z-10 space-y-6">
                            <!-- Search bar -->
                            <div class="bg-white/20 backdrop-blur-sm rounded-xl p-4 flex items-center gap-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <div class="flex-1 space-y-2">
                                    <div class="h-3 bg-white/50 rounded w-3/4"></div>
                                </div>
                            </div>
                            
                            <!-- Candidate cards -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-white/20 backdrop-blur-sm rounded-xl p-4 animate-float">
                                    <div class="w-12 h-12 bg-white rounded-full mb-3 flex items-center justify-center">
                                        <svg class="w-6 h-6 text-[#2A8FA9]" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="h-2 bg-white/50 rounded mb-2"></div>
                                    <div class="h-2 bg-white/30 rounded w-2/3 mb-3"></div>
                                    <div class="flex gap-1">
                                        <div class="h-1.5 w-1.5 bg-green-400 rounded-full"></div>
                                        <div class="h-1.5 w-1.5 bg-green-400 rounded-full"></div>
                                        <div class="h-1.5 w-1.5 bg-green-400 rounded-full"></div>
                                        <div class="h-1.5 w-1.5 bg-green-400 rounded-full"></div>
                                        <div class="h-1.5 w-1.5 bg-gray-300 rounded-full"></div>
                                    </div>
                                </div>
                                
                                <div class="bg-white/20 backdrop-blur-sm rounded-xl p-4 animate-float" style="animation-delay: 0.5s;">
                                    <div class="w-12 h-12 bg-white rounded-full mb-3 flex items-center justify-center">
                                        <svg class="w-6 h-6 text-[#2A8FA9]" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="h-2 bg-white/50 rounded mb-2"></div>
                                    <div class="h-2 bg-white/30 rounded w-2/3 mb-3"></div>
                                    <div class="flex gap-1">
                                        <div class="h-1.5 w-1.5 bg-green-400 rounded-full"></div>
                                        <div class="h-1.5 w-1.5 bg-green-400 rounded-full"></div>
                                        <div class="h-1.5 w-1.5 bg-green-400 rounded-full"></div>
                                        <div class="h-1.5 w-1.5 bg-green-400 rounded-full"></div>
                                        <div class="h-1.5 w-1.5 bg-green-400 rounded-full"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Stats -->
                            <div class="bg-white/20 backdrop-blur-sm rounded-xl p-4 flex justify-between items-center animate-float" style="animation-delay: 1s;">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-white">250+</div>
                                    <div class="text-xs text-white/80">Candidates</div>
                                </div>
                                <div class="w-px h-10 bg-white/30"></div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-white">89%</div>
                                    <div class="text-xs text-white/80">Match Rate</div>
                                </div>
                                <div class="w-px h-10 bg-white/30"></div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-white">24h</div>
                                    <div class="text-xs text-white/80">Avg Response</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Floating notification -->
                    <div class="absolute -top-4 -left-4 bg-white rounded-lg shadow-xl p-3 animate-bounce max-w-xs">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                            <span class="text-sm font-semibold text-gray-800">5 New Matches!</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="relative bg-gradient-to-br from-[#2A8FA9] via-[#409BB2] to-[#2A8FA9] text-white">
    <!-- Animated background -->
    <div class="absolute inset-0 opacity-30">
        <div class="absolute top-0 left-1/4 w-64 h-64 bg-[#F9A825] rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-white rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
    </div>
    
    <div class="absolute inset-0 pattern-dots"></div>
    
    <div class="relative z-10 py-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="inline-block mb-4 px-6 py-2 bg-white/20 backdrop-blur-sm rounded-full text-sm font-semibold animate-fade-in-up">
                🚀 Platform #1 untuk Mahasiswa & Industri
            </div>
            <h2 class="text-3xl md:text-4xl font-bold mb-4 animate-fade-in-up" style="animation-delay: 0.2s;">
                Siap Memulai Perjalanan Karir Anda?
            </h2>
            <p class="text-lg text-blue-100 mb-6 max-w-2xl mx-auto animate-fade-in-up" style="animation-delay: 0.4s;">
                Bergabunglah dengan 500+ mahasiswa dan 50+ mitra industri yang telah menemukan kesempatan di Cakrawala Connect
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center animate-fade-in-up" style="animation-delay: 0.6s;">
                <a href="/cakrawala-connect/register.php" 
                   class="group inline-flex items-center justify-center gap-2 bg-[#F9A825] hover:bg-[#F57F17] text-white font-bold py-3 px-10 rounded-lg text-base transition-all duration-300 transform hover:scale-105 shadow-2xl relative overflow-hidden">
                    <span class="relative z-10">Daftar Gratis Sekarang</span>
                    <svg class="w-5 h-5 relative z-10 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-0 group-hover:opacity-30 transform -skew-x-12 group-hover:translate-x-full transition-all duration-700"></div>
                </a>
                <a href="/cakrawala-connect/login.php" 
                   class="inline-flex items-center justify-center gap-2 bg-white/10 backdrop-blur-sm hover:bg-white/20 text-white font-bold py-3 px-10 rounded-lg text-base transition-all duration-300 border-2 border-white/30">
                    Sudah Punya Akun? Login
                </a>
            </div>
            
            <!-- Trust indicators -->
            <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4 max-w-3xl mx-auto">
                <div class="flex items-center justify-center gap-2 text-blue-100 text-sm">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>Gratis Selamanya</span>
                </div>
                <div class="flex items-center justify-center gap-2 text-blue-100 text-sm">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>Terverifikasi CDC</span>
                </div>
                <div class="flex items-center justify-center gap-2 text-blue-100 text-sm">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>Aman & Terpercaya</span>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Footer Manual -->
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

<!-- Scripts -->
<script>
// Create particles
function createParticles() {
    const container = document.getElementById('particles');
    if (!container) return;
    
    const particleCount = 50;
    
    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        
        const size = Math.random() * 6 + 2;
        const startX = Math.random() * 100;
        const startY = Math.random() * 100;
        const duration = Math.random() * 20 + 10;
        const delay = Math.random() * 5;
        
        particle.style.width = `${size}px`;
        particle.style.height = `${size}px`;
        particle.style.left = `${startX}%`;
        particle.style.top = `${startY}%`;
        particle.style.animation = `particle-float ${duration}s ease-in-out ${delay}s infinite`;
        
        container.appendChild(particle);
    }
}

// Initialize particles on load
window.addEventListener('load', createParticles);

// Scroll reveal animation
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('active');
        }
    });
}, observerOptions);

document.querySelectorAll('.scroll-reveal').forEach(el => {
    observer.observe(el);
});

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});
</script>

