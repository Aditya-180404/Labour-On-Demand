<!-- Platform Animation SVG -->
<div class="platform-animation-container">
    <svg viewBox="0 0 600 400" xmlns="http://www.w3.org/2000/svg" class="platform-svg">
        <!-- Dark Background -->
        <rect width="600" height="400" fill="#0a0e27" rx="20"/>
        
        <!-- Network Sphere Background -->
        <g class="network-sphere">
            <!-- Connection Lines -->
            <circle cx="300" cy="200" r="120" fill="none" stroke="rgba(255, 140, 0, 0.15)" stroke-width="1" class="orbit-circle"/>
            <circle cx="300" cy="200" r="90" fill="none" stroke="rgba(255, 140, 0, 0.1)" stroke-width="1" class="orbit-circle"/>
            <circle cx="300" cy="200" r="60" fill="none" stroke="rgba(255, 140, 0, 0.08)" stroke-width="1" class="orbit-circle"/>
            
            <!-- Network Lines -->
            <line x1="200" y1="120" x2="300" y2="200" stroke="rgba(255, 140, 0, 0.25)" stroke-width="1" class="network-line line-1"/>
            <line x1="400" y1="120" x2="300" y2="200" stroke="rgba(255, 140, 0, 0.25)" stroke-width="1" class="network-line line-2"/>
            <line x1="200" y1="280" x2="300" y2="200" stroke="rgba(255, 140, 0, 0.25)" stroke-width="1" class="network-line line-3"/>
            <line x1="400" y1="280" x2="300" y2="200" stroke="rgba(255, 140, 0, 0.25)" stroke-width="1" class="network-line line-4"/>
            
            <!-- Pulsing Nodes -->
            <circle cx="200" cy="120" r="3" fill="#FF8C00" class="pulse-node node-1"/>
            <circle cx="400" cy="120" r="3" fill="#FF8C00" class="pulse-node node-2"/>
            <circle cx="200" cy="280" r="3" fill="#FF8C00" class="pulse-node node-3"/>
            <circle cx="400" cy="280" r="3" fill="#FF8C00" class="pulse-node node-4"/>
            <circle cx="240" cy="100" r="2" fill="#4facfe" class="pulse-node node-5"/>
            <circle cx="360" cy="100" r="2" fill="#4facfe" class="pulse-node node-6"/>
            <circle cx="240" cy="300" r="2" fill="#4facfe" class="pulse-node node-7"/>
            <circle cx="360" cy="300" r="2" fill="#4facfe" class="pulse-node node-8"/>
        </g>
        
        <!-- Central Cloud Icon -->
        <g class="central-cloud" transform="translate(300, 200)">
            <!-- Cloud Shape -->
            <path d="M -30,-8 Q -38,-15 -30,-22 Q -22,-30 -8,-26 Q 0,-34 15,-26 Q 30,-30 34,-18 Q 42,-15 38,-4 Q 38,4 30,8 L -30,8 Z" 
                  fill="none" stroke="#FF8C00" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            <!-- Data Flow Lines inside cloud -->
            <line x1="-15" y1="-4" x2="15" y2="-4" stroke="#FF8C00" stroke-width="1.2" opacity="0.6" class="data-flow flow-1"/>
            <line x1="-12" y1="0" x2="18" y2="0" stroke="#FF8C00" stroke-width="1.2" opacity="0.6" class="data-flow flow-2"/>
            <line x1="-18" y1="4" x2="12" y2="4" stroke="#FF8C00" stroke-width="1.2" opacity="0.6" class="data-flow flow-3"/>
        </g>
        
        <!-- Orbiting Icons (Simplified) -->
        <!-- User Icon -->
        <g class="orbit-icon icon-user" transform-origin="300 200">
            <circle cx="300" cy="80" r="12" fill="#1a1f3a" stroke="#4facfe" stroke-width="1.5"/>
            <circle cx="300" cy="77" r="3" fill="#4facfe"/>
            <path d="M 295,82 Q 295,86 300,86 Q 305,86 305,82" fill="none" stroke="#4facfe" stroke-width="1.5"/>
        </g>
        
        <!-- Worker Icon -->
        <g class="orbit-icon icon-worker" transform-origin="300 200">
            <circle cx="420" cy="200" r="12" fill="#1a1f3a" stroke="#808080" stroke-width="1.5"/>
            <path d="M 415,196 L 415,194 Q 415,192 417,192 L 423,192 Q 425,192 425,194 L 425,196 M 414,196 L 426,196 Q 427,196 427,198 L 427,202 L 413,202 L 413,198 Q 413,196 414,196" 
                  fill="#808080"/>
        </g>
        
        <!-- Location Icon -->
        <g class="orbit-icon icon-location" transform-origin="300 200">
            <circle cx="300" cy="320" r="12" fill="#1a1f3a" stroke="#FF8C00" stroke-width="1.5"/>
            <path d="M 300,314 Q 297,314 297,317 Q 297,320 300,325 Q 303,320 303,317 Q 303,314 300,314" 
                  fill="none" stroke="#FF8C00" stroke-width="1.5"/>
        </g>
        
        <!-- Tools Icon -->
        <g class="orbit-icon icon-tools" transform-origin="300 200">
            <circle cx="180" cy="200" r="12" fill="#1a1f3a" stroke="#808080" stroke-width="1.5"/>
            <path d="M 177,197 L 180,194 L 183,197 M 180,194 L 180,206" fill="none" stroke="#808080" stroke-width="1.2"/>
        </g>
        
        <!-- Text Overlay -->
        <text x="300" y="360" text-anchor="middle" fill="white" font-size="28" font-weight="700" font-family="Arial, sans-serif" class="hero-text">
            Find Local Workers
        </text>
        <text x="300" y="385" text-anchor="middle" fill="rgba(255,255,255,0.8)" font-size="20" font-weight="400" font-family="Arial, sans-serif" class="hero-subtext">
            in Minutes
        </text>
        
        <!-- Data Flow Particles -->
        <circle r="2" fill="#FF8C00" class="particle particle-1">
            <animateMotion dur="4s" repeatCount="indefinite">
                <mpath href="#path1"/>
            </animateMotion>
        </circle>
        <circle r="2" fill="#4facfe" class="particle particle-2">
            <animateMotion dur="5s" repeatCount="indefinite">
                <mpath href="#path2"/>
            </animateMotion>
        </circle>
        
        <!-- Hidden paths for particle animation -->
        <defs>
            <path id="path1" d="M 200,120 Q 250,160 300,200"/>
            <path id="path2" d="M 400,120 Q 350,160 300,200"/>
        </defs>
    </svg>
</div>

<style>
.platform-animation-container {
    width: 100%;
    max-width: 600px;
    margin: 0 auto;
    padding: 20px;
}

.platform-svg {
    width: 100%;
    height: auto;
    filter: drop-shadow(0 10px 30px rgba(0, 0, 0, 0.3));
}

/* Pulsing Nodes Animation */
@keyframes pulse {
    0%, 100% {
        r: 3;
        opacity: 1;
    }
    50% {
        r: 5;
        opacity: 0.6;
    }
}

.pulse-node {
    animation: pulse 2s ease-in-out infinite;
}

.node-1 { animation-delay: 0s; }
.node-2 { animation-delay: 0.25s; }
.node-3 { animation-delay: 0.5s; }
.node-4 { animation-delay: 0.75s; }
.node-5 { animation-delay: 1s; }
.node-6 { animation-delay: 1.25s; }
.node-7 { animation-delay: 1.5s; }
.node-8 { animation-delay: 1.75s; }

/* Network Lines Shimmer */
@keyframes shimmer {
    0%, 100% {
        opacity: 0.25;
    }
    50% {
        opacity: 0.6;
    }
}

.network-line {
    animation: shimmer 3s ease-in-out infinite;
}

.line-1 { animation-delay: 0s; }
.line-2 { animation-delay: 0.75s; }
.line-3 { animation-delay: 1.5s; }
.line-4 { animation-delay: 2.25s; }

/* Data Flow Animation */
@keyframes dataFlow {
    0% {
        stroke-dashoffset: 40;
    }
    100% {
        stroke-dashoffset: 0;
    }
}

.data-flow {
    stroke-dasharray: 4 4;
    animation: dataFlow 2s linear infinite;
}

.flow-1 { animation-delay: 0s; }
.flow-2 { animation-delay: 0.3s; }
.flow-3 { animation-delay: 0.6s; }

/* Orbiting Icons Animation */
@keyframes orbit {
    0% {
        transform: rotate(0deg) translateX(120px) rotate(0deg);
    }
    100% {
        transform: rotate(360deg) translateX(120px) rotate(-360deg);
    }
}

.orbit-icon {
    animation: orbit 12s linear infinite;
}

.icon-user { animation-delay: 0s; }
.icon-worker { animation-delay: -3s; }
.icon-location { animation-delay: -6s; }
.icon-tools { animation-delay: -9s; }

/* Central Cloud Gentle Float */
@keyframes cloudFloat {
    0%, 100% {
        transform: translate(300px, 200px) translateY(0);
    }
    50% {
        transform: translate(300px, 200px) translateY(-8px);
    }
}

.central-cloud {
    animation: cloudFloat 4s ease-in-out infinite;
}

/* Orbit Circles Rotation */
@keyframes rotateOrbit {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.orbit-circle {
    transform-origin: center;
    animation: rotateOrbit 30s linear infinite;
}

/* Text Glow */
.hero-text {
    filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.3));
}

.hero-subtext {
    filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.2));
}

/* Particles */
.particle {
    opacity: 0.7;
}

/* Responsive */
@media (max-width: 768px) {
    .platform-animation-container {
        max-width: 100%;
        padding: 10px;
    }
    
    .hero-text {
        font-size: 20px;
    }
    
    .hero-subtext {
        font-size: 16px;
    }
}
</style>
        <!-- Network Sphere Background -->
        <g class="network-sphere">
            <!-- Connection Lines -->
            <circle cx="400" cy="300" r="200" fill="none" stroke="rgba(255, 140, 0, 0.1)" stroke-width="1" class="orbit-circle"/>
            <circle cx="400" cy="300" r="160" fill="none" stroke="rgba(255, 140, 0, 0.08)" stroke-width="1" class="orbit-circle"/>
            <circle cx="400" cy="300" r="120" fill="none" stroke="rgba(255, 140, 0, 0.06)" stroke-width="1" class="orbit-circle"/>
            
            <!-- Network Lines -->
            <line x1="250" y1="200" x2="400" y2="300" stroke="rgba(255, 140, 0, 0.2)" stroke-width="1" class="network-line line-1"/>
            <line x1="550" y1="200" x2="400" y2="300" stroke="rgba(255, 140, 0, 0.2)" stroke-width="1" class="network-line line-2"/>
            <line x1="250" y1="400" x2="400" y2="300" stroke="rgba(255, 140, 0, 0.2)" stroke-width="1" class="network-line line-3"/>
            <line x1="550" y1="400" x2="400" y2="300" stroke="rgba(255, 140, 0, 0.2)" stroke-width="1" class="network-line line-4"/>
            
            <!-- Pulsing Nodes -->
            <circle cx="250" cy="200" r="4" fill="#FF8C00" class="pulse-node node-1"/>
            <circle cx="550" cy="200" r="4" fill="#FF8C00" class="pulse-node node-2"/>
            <circle cx="250" cy="400" r="4" fill="#FF8C00" class="pulse-node node-3"/>
            <circle cx="550" cy="400" r="4" fill="#FF8C00" class="pulse-node node-4"/>
            <circle cx="320" cy="150" r="3" fill="#4facfe" class="pulse-node node-5"/>
            <circle cx="480" cy="150" r="3" fill="#4facfe" class="pulse-node node-6"/>
            <circle cx="320" cy="450" r="3" fill="#4facfe" class="pulse-node node-7"/>
            <circle cx="480" cy="450" r="3" fill="#4facfe" class="pulse-node node-8"/>
        </g>
        
        <!-- Central Cloud Icon -->
        <g class="central-cloud" transform="translate(400, 300)">
            <!-- Cloud Shape -->
            <path d="M -40,-10 Q -50,-20 -40,-30 Q -30,-40 -10,-35 Q 0,-45 20,-35 Q 40,-40 45,-25 Q 55,-20 50,-5 Q 50,5 40,10 L -40,10 Z" 
                  fill="none" stroke="#FF8C00" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            <!-- Data Flow Lines inside cloud -->
            <line x1="-20" y1="-5" x2="20" y2="-5" stroke="#FF8C00" stroke-width="1.5" opacity="0.6" class="data-flow flow-1"/>
            <line x1="-15" y1="0" x2="25" y2="0" stroke="#FF8C00" stroke-width="1.5" opacity="0.6" class="data-flow flow-2"/>
            <line x1="-25" y1="5" x2="15" y2="5" stroke="#FF8C00" stroke-width="1.5" opacity="0.6" class="data-flow flow-3"/>
        </g>
        
        <!-- Orbiting Icons -->
        <!-- User/Customer Icon -->
        <g class="orbit-icon icon-user" transform-origin="400 300">
            <circle cx="400" cy="100" r="20" fill="white" stroke="#4facfe" stroke-width="2"/>
            <path d="M 400,95 Q 395,90 400,85 Q 405,90 400,95 M 400,100 Q 395,105 390,110 L 390,115 L 410,115 L 410,110 Q 405,105 400,100" 
                  fill="#4facfe" transform="translate(0, -5)"/>
        </g>
        
        <!-- Worker/Helmet Icon -->
        <g class="orbit-icon icon-worker" transform-origin="400 300">
            <circle cx="600" cy="300" r="20" fill="white" stroke="#808080" stroke-width="2"/>
            <path d="M 590,295 L 590,290 Q 590,285 595,285 L 605,285 Q 610,285 610,290 L 610,295 M 588,295 L 612,295 Q 615,295 615,300 L 615,305 L 585,305 L 585,300 Q 585,295 588,295" 
                  fill="#808080"/>
        </g>
        
        <!-- Location Pin Icon -->
        <g class="orbit-icon icon-location" transform-origin="400 300">
            <circle cx="400" cy="500" r="20" fill="white" stroke="#FF8C00" stroke-width="2"/>
            <path d="M 400,490 Q 395,490 395,495 Q 395,500 400,510 Q 405,500 405,495 Q 405,490 400,490 M 400,493 Q 398,493 398,495 Q 398,497 400,497 Q 402,497 402,495 Q 402,493 400,493" 
                  fill="#FF8C00"/>
        </g>
        
        <!-- Tools Icon -->
        <g class="orbit-icon icon-tools" transform-origin="400 300">
            <circle cx="200" cy="300" r="20" fill="white" stroke="#808080" stroke-width="2"/>
            <path d="M 195,295 L 200,290 L 205,295 M 200,290 L 200,310 M 197,305 L 203,305" fill="none" stroke="#808080" stroke-width="1.5"/>
            <circle cx="203" cy="297" r="2" fill="#808080"/>
        </g>
        
        <!-- Shield/Verified Icon -->
        <g class="orbit-icon icon-shield" transform-origin="400 300">
            <circle cx="480" cy="180" r="18" fill="white" stroke="#00c853" stroke-width="2"/>
            <path d="M 480,172 L 475,182 Q 475,187 480,192 Q 485,187 485,182 Z M 477,182 L 480,186 L 485,178" 
                  fill="none" stroke="#00c853" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </g>
        
        <!-- Task/Checklist Icon -->
        <g class="orbit-icon icon-task" transform-origin="400 300">
            <circle cx="320" cy="420" r="18" fill="white" stroke="#4facfe" stroke-width="2"/>
            <rect x="313" y="413" width="14" height="14" rx="2" fill="none" stroke="#4facfe" stroke-width="1.5"/>
            <path d="M 315,418 L 318,421 L 323,416" fill="none" stroke="#4facfe" stroke-width="1.5" stroke-linecap="round"/>
        </g>
        
        <!-- Data Flow Particles -->
        <circle r="3" fill="#FF8C00" class="particle particle-1">
            <animateMotion dur="4s" repeatCount="indefinite">
                <mpath href="#path1"/>
            </animateMotion>
        </circle>
        <circle r="3" fill="#4facfe" class="particle particle-2">
            <animateMotion dur="5s" repeatCount="indefinite">
                <mpath href="#path2"/>
            </animateMotion>
        </circle>
        
        <!-- Hidden paths for particle animation -->
        <defs>
            <path id="path1" d="M 250,200 Q 325,250 400,300"/>
            <path id="path2" d="M 550,200 Q 475,250 400,300"/>
        </defs>
    </svg>
</div>

<style>
.platform-animation-container {
    width: 100%;
    max-width: 800px;
    margin: 0 auto;
    padding: 40px 20px;
}

.platform-svg {
    width: 100%;
    height: auto;
}

/* Pulsing Nodes Animation */
@keyframes pulse {
    0%, 100% {
        r: 4;
        opacity: 1;
    }
    50% {
        r: 6;
        opacity: 0.6;
    }
}

.pulse-node {
    animation: pulse 2s ease-in-out infinite;
}

.node-1 { animation-delay: 0s; }
.node-2 { animation-delay: 0.25s; }
.node-3 { animation-delay: 0.5s; }
.node-4 { animation-delay: 0.75s; }
.node-5 { animation-delay: 1s; }
.node-6 { animation-delay: 1.25s; }
.node-7 { animation-delay: 1.5s; }
.node-8 { animation-delay: 1.75s; }

/* Network Lines Shimmer */
@keyframes shimmer {
    0%, 100% {
        opacity: 0.2;
    }
    50% {
        opacity: 0.6;
    }
}

.network-line {
    animation: shimmer 3s ease-in-out infinite;
}

.line-1 { animation-delay: 0s; }
.line-2 { animation-delay: 0.75s; }
.line-3 { animation-delay: 1.5s; }
.line-4 { animation-delay: 2.25s; }

/* Data Flow Animation */
@keyframes dataFlow {
    0% {
        stroke-dashoffset: 50;
    }
    100% {
        stroke-dashoffset: 0;
    }
}

.data-flow {
    stroke-dasharray: 5 5;
    animation: dataFlow 2s linear infinite;
}

.flow-1 { animation-delay: 0s; }
.flow-2 { animation-delay: 0.3s; }
.flow-3 { animation-delay: 0.6s; }

/* Orbiting Icons Animation */
@keyframes orbit {
    0% {
        transform: rotate(0deg) translateX(200px) rotate(0deg);
    }
    100% {
        transform: rotate(360deg) translateX(200px) rotate(-360deg);
    }
}

.orbit-icon {
    animation: orbit 12s linear infinite;
}

.icon-user { animation-delay: 0s; }
.icon-worker { animation-delay: -3s; }
.icon-location { animation-delay: -6s; }
.icon-tools { animation-delay: -9s; }
.icon-shield { animation-delay: -1.5s; }
.icon-task { animation-delay: -7.5s; }

/* Icon Hover Effect */
.orbit-icon {
    transition: all 0.3s ease;
    cursor: pointer;
}

.orbit-icon:hover {
    filter: drop-shadow(0 0 8px rgba(255, 140, 0, 0.6));
}

/* Central Cloud Gentle Float */
@keyframes cloudFloat {
    0%, 100% {
        transform: translate(400px, 300px) translateY(0);
    }
    50% {
        transform: translate(400px, 300px) translateY(-10px);
    }
}

.central-cloud {
    animation: cloudFloat 4s ease-in-out infinite;
}

/* Orbit Circles Rotation */
@keyframes rotateOrbit {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.orbit-circle {
    transform-origin: center;
    animation: rotateOrbit 30s linear infinite;
}

/* Particles */
.particle {
    opacity: 0.7;
}

/* Responsive */
@media (max-width: 768px) {
    .platform-animation-container {
        padding: 20px 10px;
    }
}
</style>
