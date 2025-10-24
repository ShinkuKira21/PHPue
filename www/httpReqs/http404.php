<!-- APP WRAPPER -->
<div class="flex items-center justify-center min-h-screen p-4">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-2xl p-8 text-center transition-all duration-300 hover:scale-105">
        <!-- Animated 404 Icon -->
        <div class="relative mb-6">
            <div class="w-24 h-24 bg-gradient-to-r from-red-500 to-pink-600 rounded-full mx-auto flex items-center justify-center shadow-lg">
                <i class="fas fa-exclamation-triangle text-white text-3xl"></i>
            </div>
            <div class="absolute -top-2 -right-2 w-8 h-8 bg-red-600 rounded-full flex items-center justify-center">
                <span class="text-white text-sm font-bold">!</span>
            </div>
        </div>

        <!-- Error Code -->
        <h1 class="text-8xl font-bold text-gray-900 mb-2">404</h1>
        
        <!-- Error Message -->
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Page Not Found</h2>
        
        <!-- Description -->
        <p class="text-gray-600 mb-8 leading-relaxed text-lg">
            Oops! The page you're looking for seems to have wandered off into the digital void. 
            Don't worry, even the best explorers sometimes take wrong turns.
        </p>

        <!-- Requested URL (if available) -->
        <?php if(isset($_SERVER['REQUEST_URI'])): ?>
        <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-300">
            <p class="text-sm text-gray-600 mb-1 font-medium">You were looking for:</p>
            <p class="text-gray-800 font-mono text-sm break-all bg-white p-2 rounded border"><?= htmlspecialchars($_SERVER['REQUEST_URI']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="/" class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white px-6 py-3 rounded-lg font-semibold hover:from-blue-700 hover:to-indigo-800 transition-all duration-300 hover:-translate-y-1 shadow-lg flex items-center justify-center gap-2">
                <i class="fas fa-home"></i>
                Go Home
            </a>
            <button onclick="history.back()" class="border-2 border-gray-400 text-gray-800 px-6 py-3 rounded-lg font-semibold hover:border-gray-500 hover:bg-gray-100 transition-all duration-300 hover:-translate-y-1 flex items-center justify-center gap-2">
                <i class="fas fa-arrow-left"></i>
                Go Back
            </button>
        </div>

        <!-- Additional Help -->
        <div class="mt-8 pt-6 border-t border-gray-300">
            <p class="text-sm text-gray-600 mb-4 font-medium">Need help?</p>
            <div class="flex justify-center space-x-6">
                <a href="#" class="text-gray-500 hover:text-blue-600 transition-colors duration-300 text-2xl">
                    <i class="fas fa-envelope"></i>
                </a>
                <a href="#" class="text-gray-500 hover:text-green-600 transition-colors duration-300 text-2xl">
                    <i class="fas fa-question-circle"></i>
                </a>
                <a href="#" class="text-gray-500 hover:text-purple-600 transition-colors duration-300 text-2xl">
                    <i class="fas fa-comments"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Floating decorative elements -->
<div class="fixed top-10 left-10 w-20 h-20 bg-blue-300 rounded-full opacity-20 animate-pulse pointer-events-none"></div>
<div class="fixed bottom-20 right-16 w-16 h-16 bg-purple-300 rounded-full opacity-30 animate-bounce pointer-events-none"></div>
<div class="fixed top-1/3 right-20 w-12 h-12 bg-pink-300 rounded-full opacity-25 animate-pulse delay-1000 pointer-events-none"></div>

<style>
    @keyframes float {
        0%, 100% { 
            transform: translateY(0px); 
        }
        50% { 
            transform: translateY(-10px); 
        }
    }
    .animate-float {
        animation: float 3s ease-in-out infinite;
    }
    
    @keyframes bounce {
        0%, 100% {
            transform: translateY(-25%);
            animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
        }
        50% {
            transform: translateY(0);
            animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
        }
    }
    .animate-bounce {
        animation: bounce 1s infinite;
    }
    
    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: .5;
        }
    }
    .animate-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
</style>
<!-- END OF APP WRAPPER -->