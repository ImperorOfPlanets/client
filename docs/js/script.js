/**
 * Flying Sphere — Летающая Сфера
 * Конструктор AI-помощников будущего
 * Мультиязычность: RU / EN / ZH
 */

// ========================================
// 1. ПЕРЕВОДЫ
// ========================================
const translations = {
    ru: {
        title: 'Flying Sphere',
        subtitle: 'Летающая Сфера — конструктор будущего, который поможет вам создавать умных помощников и автоматизировать жизнь',
        about_title: '🚀 Что мы создаём?',
        constructor_title: 'Конструктор будущего',
        constructor_desc: '<strong>Flying Sphere</strong> — это платформа, где каждый сможет создать своего персонального AI-помощника <em>без знания программирования</em>. Перетаскивайте блоки, настраивайте логику, подключайте устройства — и ваш цифровой помощник готов!',
        ai_title: 'Умная автоматизация',
        ai_desc: 'Автоматизируйте рутину: от управления умным домом до анализа данных. Летающая Сфера научится вашим привычкам и будет помогать 24/7.',
        open_title: 'Открытый проект',
        open_desc: 'Мы верим в open source. Код будет доступен всем, чтобы каждый мог улучшить платформу и создать что-то уникальное для себя и других.',
        help_title: '💙 Почему нам нужна ваша помощь?',
        help_call: '🙏 <strong>Ваша поддержка — это не просто деньги. Это вера в проект, который изменит будущее.</strong>',
        features_title: '✨ Возможности',
        feature1_title: 'Визуальный конструктор',
        feature1_desc: 'Собирайте логику помощника из блоков, как конструктор LEGO. Никакого кода — только интуиция.',
        feature2_title: 'Интеграции',
        feature2_desc: 'Подключайте Telegram, VK, умный дом, биржи, погодные сервисы — что угодно через простые плагины.',
        feature3_title: 'AI-ядро',
        feature3_desc: 'Встроенная поддержка нейросетей: ваш помощник будет понимать естественный язык и учиться на ваших действиях.',
        feature4_title: 'Работает везде',
        feature4_desc: 'Веб, десктоп, мобильные устройства, Raspberry Pi — Летающая Сфера запустится там, где нужно вам.',
        feature5_title: 'Приватность',
        feature5_desc: 'Ваши данные остаются у вас. Локальное хранение, шифрование, полный контроль над доступом.',
        feature6_title: 'Мультиязычность',
        feature6_desc: 'Интерфейс и помощник говорят на вашем языке: русский, английский, китайский и другие.',
        donate_title: '🙏 Поддержите Flying Sphere',
        donate_desc: '<strong>Мы создаём инструмент будущего, но не можем сделать это в одиночку.</strong><br>Каждая копейка идёт на серверы, разработку и тестирование. Помогите нам запустить Летающую Сферу!',
        custom: 'Своя сумма',
        thanks: '🙏 Спасибо! Вы помогаете создать инструмент, который будет служить людям.',
        footer: 'Flying Sphere — Летающая Сфера | Open Source проект для создания AI-помощников'
    },
    en: {
        title: 'Flying Sphere',
        subtitle: 'Flying Sphere is a future builder that helps you create smart assistants and automate your life',
        about_title: '🚀 What are we building?',
        constructor_title: 'Builder of the Future',
        constructor_desc: '<strong>Flying Sphere</strong> is a platform where anyone can create their personal AI assistant <em>without coding</em>. Drag blocks, configure logic, connect devices — your digital helper is ready!',
        ai_title: 'Smart Automation',
        ai_desc: 'Automate routine tasks: from smart home control to data analysis. Flying Sphere learns your habits and helps 24/7.',
        open_title: 'Open Source',
        open_desc: 'We believe in open source. The code will be available to everyone to improve the platform and create something unique.',
        help_title: '💙 Why do we need your help?',
        help_call: '🙏 <strong>Your support is not just money. It\'s belief in a project that will change the future.</strong>',
        features_title: '✨ Features',
        feature1_title: 'Visual Builder',
        feature1_desc: 'Build assistant logic with blocks like LEGO. No code needed — just intuition.',
        feature2_title: 'Integrations',
        feature2_desc: 'Connect Telegram, VK, smart home, exchanges, weather services — anything via simple plugins.',
        feature3_title: 'AI Core',
        feature3_desc: 'Built-in neural network support: your assistant understands natural language and learns from your actions.',
        feature4_title: 'Runs Everywhere',
        feature4_desc: 'Web, desktop, mobile, Raspberry Pi — Flying Sphere runs wherever you need it.',
        feature5_title: 'Privacy First',
        feature5_desc: 'Your data stays with you. Local storage, encryption, full access control.',
        feature6_title: 'Multilingual',
        feature6_desc: 'Interface and assistant speak your language: Russian, English, Chinese, and more.',
        donate_title: '🙏 Support Flying Sphere',
        donate_desc: '<strong>We\'re building a tool for the future, but we can\'t do it alone.</strong><br>Every ruble goes to servers, development, and testing. Help us launch Flying Sphere!',
        custom: 'Custom Amount',
        thanks: '🙏 Thank you! You\'re helping create a tool that will serve people.',
        footer: 'Flying Sphere | Open Source project for building AI assistants'
    },
    zh: {
        title: 'Flying Sphere',
        subtitle: '飞行球体 — 未来构建器，助您创建智能助手并自动化生活',
        about_title: '🚀 我们在构建什么？',
        constructor_title: '未来构建器',
        constructor_desc: '<strong>Flying Sphere</strong> 是一个平台，任何人都可以 <em>无需编程</em> 创建个人 AI 助手。拖放模块、配置逻辑、连接设备 — 您的数字助手即刻就绪！',
        ai_title: '智能自动化',
        ai_desc: '自动化日常任务：从智能家居控制到数据分析。飞行球体学习您的习惯，全天候提供帮助。',
        open_title: '开源项目',
        open_desc: '我们信奉开源。代码将对所有人开放，以便改进平台并创造独特价值。',
        help_title: '💙 为什么需要您的帮助？',
        help_call: '🙏 <strong>您的支持不仅是金钱，更是对改变未来项目的信念。</strong>',
        features_title: '✨ 功能特点',
        feature1_title: '可视化构建器',
        feature1_desc: '像乐高一样用模块构建助手逻辑。无需代码，只需直觉。',
        feature2_title: '集成扩展',
        feature2_desc: '连接 Telegram、VK、智能家居、交易所、天气服务 — 通过简单插件连接一切。',
        feature3_title: 'AI 核心',
        feature3_desc: '内置神经网络支持：您的助手理解自然语言并从您的行为中学习。',
        feature4_title: '随处运行',
        feature4_desc: 'Web、桌面、移动设备、树莓派 — 飞行球体在您需要的任何地方运行。',
        feature5_title: '隐私优先',
        feature5_desc: '您的数据由您掌控。本地存储、加密、完整的访问控制。',
        feature6_title: '多语言支持',
        feature6_desc: '界面和助手使用您的语言：俄语、英语、中文等。',
        donate_title: '🙏 支持 Flying Sphere',
        donate_desc: '<strong>我们正在构建未来工具，但无法独自完成。</strong><br>每一分钱都用于服务器、开发和测试。帮助我们启动飞行球体！',
        custom: '自定义金额',
        thanks: '🙏 谢谢！您正在帮助创建服务于人类的工具。',
        footer: 'Flying Sphere | 构建 AI 助手的开源项目'
    }
};

// ========================================
// 2. КОНФИГУРАЦИЯ
// ========================================
const themeConfig = {
    ru: { theme: 'ru', flag: '🇷🇺' },
    en: { theme: 'en', flag: '🇺🇸' },
    zh: { theme: 'cn', flag: '🇨🇳' }
};

const IMAGE_SIZES = [640, 1024, 1920];
let selectedDonationAmount = null;

// ========================================
// 3. ОСНОВНЫЕ ФУНКЦИИ
// ========================================
function switchLanguage(lang) {
    const config = themeConfig[lang];
    if (!config || !translations[lang]) return;

    // Обновляем текст
    document.querySelectorAll('[data-key]').forEach(el => {
        const key = el.getAttribute('data-key');
        const newText = translations[lang][key];
        if (newText) {
            el.style.opacity = '0';
            setTimeout(() => {
                if (el.tagName === 'STRONG' || el.tagName === 'EM') {
                    el.parentElement.innerHTML = newText;
                } else {
                    el.innerHTML = newText;
                }
                el.style.opacity = '1';
            }, 150);
        }
    });

    // Обновляем визуал
    updateHeaderVisuals(config.theme);
    updateActiveButton(lang);
    
    // Сохраняем выбор
    localStorage.setItem('siteLang', lang);
    document.documentElement.lang = lang;
}

function updateHeaderVisuals(theme) {
    const header = document.getElementById('mainHeader');
    const headerImg = document.getElementById('headerImage');
    const headerSource = document.getElementById('headerSource');
    
    if (!header) return;

    ['ru', 'cn', 'en'].forEach(t => header.classList.remove(`header-${t}`));
    header.classList.add(`header-${theme}`);

    if (headerSource) {
        const srcset = IMAGE_SIZES.map(w => `images/${theme}/header-${w}.webp ${w}w`).join(', ');
        headerSource.srcset = srcset;
    }

    if (headerImg) {
        headerImg.style.opacity = '0';
        setTimeout(() => {
            headerImg.src = `images/${theme}/header-1920.webp`;
            headerImg.style.opacity = '1';
        }, 200);
    }
}

function updateActiveButton(lang) {
    document.querySelectorAll('.lang-flag-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.lang === lang);
    });
}

// ========================================
// 4. ОБРАБОТЧИКИ ДОНАТОВ
// ========================================
function selectAmount(amount) {
    document.querySelectorAll('.donation-amount').forEach(el => el.classList.remove('selected', 'active'));
    
    document.querySelectorAll('.donation-amount').forEach(btn => {
        if (btn.textContent.includes(amount) || (amount === 'custom' && btn.dataset.key === 'custom')) {
            btn.classList.add('selected', 'active');
        }
    });

    if (amount === 'custom') {
        const custom = prompt('Введите сумму в рублях:');
        if (custom && !isNaN(custom) && parseFloat(custom) > 0) {
            selectedDonationAmount = parseFloat(custom);
            processDonation();
        }
    } else {
        selectedDonationAmount = amount;
    }
}

function processDonation() {
    const amount = selectedDonationAmount || 300;
    const lang = localStorage.getItem('siteLang') || 'ru';
    
    // По умолчанию открываем ЮMoney для всех (работает с картами РФ)
    window.open(`https://yoomoney.ru/to/4100118512155962`, '_blank', 'noopener,noreferrer');
}

function showToast(message) {
    let toast = document.getElementById('toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast';
        toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#1e293b;color:white;padding:14px 28px;border-radius:12px;z-index:9999;opacity:0;transition:opacity 0.3s;pointer-events:none;font-weight:500;box-shadow:0 10px 40px rgba(0,0,0,0.3);';
        document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.style.opacity = '1';
    setTimeout(() => { toast.style.opacity = '0'; }, 3500);
}

// ========================================
// 5. ИНИЦИАЛИЗАЦИЯ
// ========================================
function initApp() {
    // Год в футере
    const yearEl = document.getElementById('currentYear');
    if (yearEl) yearEl.textContent = new Date().getFullYear();

    // Плавность для текста
    document.querySelectorAll('[data-key]').forEach(el => {
        el.style.transition = 'opacity 0.2s ease';
        el.style.opacity = '1';
    });

    // Обработчики языка
    document.querySelectorAll('.lang-flag-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const lang = e.currentTarget.dataset.lang;
            if (lang && lang !== localStorage.getItem('siteLang')) {
                switchLanguage(lang);
            }
        });
    });

    // Загружаем сохранённый язык
    const savedLang = localStorage.getItem('siteLang') || 'ru';
    switchLanguage(savedLang);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}