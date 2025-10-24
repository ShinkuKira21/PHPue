
<!-- APP WRAPPER -->
    <div class="max-w-md w-full bg-white rounded-2xl shadow-2xl p-8 text-center transform hover:scale-105 transition-transform duration-300">
        <!-- Animated 404 Icon -->
            <div class="w-24 h-24 bg-gradient-to-r from-red-400 to-pink-500 rounded-full mx-auto flex items-center justify-center shadow-lg">
                <i class="fas fa-exclamation-triangle text-white text-3xl"></i>
            </div>
            <div class="absolute -top-2 -right-2 w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                <span class="text-white text-sm font-bold">!</span>
            </div>
        </div>

        <!-- Error Code -->
        <h1 class="text-8xl font-bold text-gray-800 mb-2">404</h1>
        
        <!-- Error Message -->
        <h2 class="text-2xl font-semibold text-gray-700 mb-4">Page Not Found</h2>
        
        <!-- Description -->
        <p class="text-gray-600 mb-8 leading-relaxed">
            Oops! The page you're looking for seems to have wandered off into the digital void. 
            Don't worry, even the best explorers sometimes take wrong turns.
        </p>

        <!-- Requested URL (if available) -->
        <?php if(isset($_SERVER['REQUEST_URI'])): ?>
        <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
            <p class="text-sm text-gray-500 mb-1">You were looking for:</p>
            <p class="text-gray-700 font-mono text-sm break-all"><?= htmlspecialchars($_SERVER['REQUEST_URI']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="/" class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:from-blue-600 hover:to-indigo-700 transition-all duration-300 transform hover:-translate-y-1 shadow-lg flex items-center justify-center gap-2">
                <i class="fas fa-home"></i>
                Go Home
            </a>
            <button onclick="history.back()" class="border-2 border-gray-300 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:border-gray-400 hover:bg-gray-50 transition-all duration-300 transform hover:-translate-y-1 flex items-center justify-center gap-2">
                <i class="fas fa-arrow-left"></i>
                Go Back
            </button>
        </div>

        <!-- Additional Help -->
        <div class="mt-8 pt-6 border-t border-gray-200">
            <p class="text-sm text-gray-500 mb-4">Need help?</p>
            <div class="flex justify-center space-x-6">
                <a href="#" class="text-gray-400 hover:text-blue-500 transition-colors duration-300">
                    <i class="fas fa-envelope text-xl"></i>
                </a>
                <a href="#" class="text-gray-400 hover:text-green-500 transition-colors duration-300">
                    <i class="fas fa-question-circle text-xl"></i>
                </a>
                <a href="#" class="text-gray-400 hover:text-purple-500 transition-colors duration-300">
                    <i class="fas fa-comments text-xl"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Floating decorative elements -->
    <div class="absolute top-10 left-10 w-20 h-20 bg-blue-200 rounded-full opacity-20 animate-pulse"></div>
    <div class="absolute bottom-20 right-16 w-16 h-16 bg-purple-200 rounded-full opacity-30 animate-bounce"></div>
    <div class="absolute top-1/3 right-20 w-12 h-12 bg-pink-200 rounded-full opacity-25 animate-pulse delay-75"></div>

    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
    </style>
<!-- END OF APP WRAPPER -->