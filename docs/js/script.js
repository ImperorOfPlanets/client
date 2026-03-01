/**
 * Starter Sphere — Language & Theme Switcher
 * Три языка: Россия / Китай / США
 */

// ========================================
// 1. ПЕРЕВОДЫ (текстовый контент)
// ========================================
const translations = {
    ru: {
        opensource: 'Open Source Проект',
        title: 'Starter Sphere',
        subtitle: 'Платформа вашего персонального AI помощника. Создайте свою летающую сферу с минимальными знаниями кода.',
        github: 'GitHub',
        donate: 'Поддержать',
        try_demo: 'Попробовать',
        features: 'Возможности',
        feature1_title: 'AI Помощник',
        feature1_desc: 'Создайте своего персонального AI помощника с обработкой естественного языка и машинным обучением.',
        feature2_title: 'No-Code Конструктор',
        feature2_desc: 'Создавайте сложные автоматизации и рабочие процессы без написания кода.',
        feature3_title: 'Летающая Сфера',
        feature3_desc: 'Готовая архитектура для интеграции автономных дронов и IoT устройств.',
        support_title: 'Поддержите Проект',
        support_desc: 'Помогите нам создать будущее персональных AI помощников. Ваш вклад позволяет нам разрабатывать новые функции и сохранять проект open-source.',
        custom: 'Своя',
        regional_payment: 'Региональные способы оплаты',
        contribute: 'Внесите вклад',
        contribute_desc: 'Присоединяйтесь к нашему сообществу разработчиков и помогите создать будущее AI помощников',
        footer: 'Open Source проект для персональных AI помощников'
    },
    en: {
        opensource: 'Open Source Project',
        title: 'Starter Sphere',
        subtitle: 'Your Personal AI Assistant Platform. Build your flying sphere with minimal coding knowledge.',
        github: 'GitHub',
        donate: 'Support Project',
        try_demo: 'Try Demo',
        features: 'Features',
        feature1_title: 'AI Assistant',
        feature1_desc: 'Create your personal AI assistant with natural language processing and machine learning capabilities.',
        feature2_title: 'No-Code Builder',
        feature2_desc: 'Build complex automations and workflows without writing a single line of code.',
        feature3_title: 'Flying Sphere',
        feature3_desc: 'Future-ready architecture for autonomous drone and IoT device integration.',
        support_title: 'Support Our Project',
        support_desc: 'Help us build the future of personal AI assistants. Your contribution enables us to develop new features and keep the project open-source.',
        custom: 'Custom',
        regional_payment: 'Regional Payment Methods',
        contribute: 'Contribute',
        contribute_desc: 'Join our community of developers and help shape the future of AI assistants',
        footer: 'Open Source Project for Personal AI Assistants'
    },
    zh: {
        opensource: '开源项目',
        title: 'Starter Sphere',
        subtitle: '您的个人 AI 助手平台。用最少的编码知识构建您的飞行球体。',
        github: 'GitHub',
        donate: '支持项目',
        try_demo: '试用演示',
        features: '功能特点',
        feature1_title: 'AI 助手',
        feature1_desc: '创建具有自然语言处理和机器学习能力的个人 AI 助手。',
        feature2_title: '无代码构建器',
        feature2_desc: '无需编写一行代码即可构建复杂的自动化和工作流程。',
        feature3_title: '飞行球体',
        feature3_desc: '为自主无人机和物联网设备集成提供面向未来的架构。',
        support_title: '支持我们的项目',
        support_desc: '帮助我们构建个人 AI 助手的未来。您的贡献使我们能够开发新功能并保持项目开源。',
        custom: '自定义',
        regional_payment: '区域支付方式',
        contribute: '贡献',
        contribute_desc: '加入我们的开发者社区，帮助塑造 AI 助手的未来',
        footer: '个人 AI 助手开源项目'
    }
};

// ========================================
// 2. КОНФИГУРАЦИЯ ТЕМ (язык → визуал)
// ========================================
const themeConfig = {
    ru: { theme: 'ru', emoji: '🐻', flag: '🇷🇺' },
    zh: { theme: 'cn', emoji: '🐉', flag: '🇨🇳' },
    en: { theme: 'en', emoji: '🦅', flag: '🇺🇸' }
};

// Доступные размеры изображений для srcset
const IMAGE_SIZES = [640, 1024, 1920];

// ========================================
// 3. ГЛАВНАЯ ФУНКЦИЯ ПЕРЕКЛЮЧЕНИЯ
// ========================================
function switchLanguage(lang) {
    const config = themeConfig[lang];
    if (!config || !translations[lang]) {
        console.warn(`Language "${lang}" not configured`);
        return;
    }

    // 🔹 3.1 Обновляем ТЕКСТ с плавным переходом
    document.querySelectorAll('[data-translate]').forEach(el => {
        const key = el.getAttribute('data-translate');
        const newText = translations[lang][key];
        
        if (newText) {
            el.style.opacity = '0';
            setTimeout(() => {
                el.textContent = newText;
                el.style.opacity = '1';
            }, 150);
        }
    });

    // 🔹 3.2 Обновляем ВИЗУАЛ хедера (фон + классы темы)
    updateHeaderVisuals(config.theme);

    // 🔹 3.3 Обновляем СФЕРУ (цвет + эмодзи)
    updateSphere(config.theme, config.emoji);

    // 🔹 3.4 Обновляем активную кнопку в селекторе
    updateActiveButton(lang);

    // 🔹 3.5 Сохраняем выбор пользователя
    localStorage.setItem('siteLang', lang);
    document.documentElement.lang = lang;

    // 🔹 3.6 Синхронизируем со старым переключателем стран (если есть)
    syncLegacyCountrySelector(config.theme);
}

// ========================================
// 4. ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ========================================

function updateHeaderVisuals(theme) {
    const header = document.getElementById('mainHeader');
    const headerImg = document.getElementById('headerImage');
    const headerSource = document.getElementById('headerSource');
    
    if (!header) return;

    // Удаляем старые классы тем, добавляем новый
    ['ru', 'cn', 'en'].forEach(t => header.classList.remove(`header-${t}`));
    header.classList.add(`header-${theme}`);

    // Обновляем responsive images через srcset
    if (headerSource) {
        const srcset = IMAGE_SIZES
            .map(w => `images/${theme}/header-${w}.webp ${w}w`)
            .join(', ');
        headerSource.srcset = srcset;
    }

    // Плавная замена изображения
    if (headerImg) {
        headerImg.style.opacity = '0';
        setTimeout(() => {
            headerImg.src = `images/${theme}/header-1920.webp`;
            headerImg.style.opacity = '1';
        }, 200);
    }
}

function updateSphere(theme, emoji) {
    const sphere = document.getElementById('previewSphere');
    const emojiEl = document.getElementById('sphereEmoji');

    if (sphere) {
        ['ru', 'cn', 'en'].forEach(t => sphere.classList.remove(t));
        sphere.classList.add(theme);
    }

    if (emojiEl && emoji) {
        emojiEl.style.opacity = '0';
        setTimeout(() => {
            emojiEl.textContent = emoji;
            emojiEl.style.opacity = '1';
        }, 150);
    }
}

function updateActiveButton(lang) {
    document.querySelectorAll('.lang-flag-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.lang === lang);
    });
}

function syncLegacyCountrySelector(theme) {
    const countryMap = { ru: 'russia', cn: 'china', en: 'usa' };
    const country = countryMap[theme];
    
    const header = document.getElementById('mainHeader');
    if (!header) return;

    // Обновляем классы для обратной совместимости
    ['country-header', 'header-russia', 'header-china', 'header-usa']
        .forEach(c => header.classList.remove(c));
    header.classList.add('country-header', `header-${country}`);

    // Показываем нужную платежную секцию
    document.querySelectorAll('.russia-payment, .china-payment, .usa-payment')
        .forEach(el => el.classList.add('d-none'));
    
    const paymentEl = document.querySelector(`.${country}-payment`);
    if (paymentEl) paymentEl.classList.remove('d-none');

    // Обновляем кнопки стран
    document.querySelectorAll('.country-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.country === country);
    });
}

// ========================================
// 5. ОБРАБОТЧИКИ СОБЫТИЙ
// ========================================

function handleLanguageClick(event) {
    const btn = event.currentTarget;
    const lang = btn.dataset.lang;
    
    if (lang && lang !== localStorage.getItem('siteLang')) {
        switchLanguage(lang);
    }
}

function handleCountryClick(event) {
    // Для обратной совместимости: страна → язык
    const country = event.currentTarget.dataset.country;
    const langMap = { russia: 'ru', china: 'zh', usa: 'en' };
    const lang = langMap[country];
    
    if (lang) {
        switchLanguage(lang);
    }
}

function selectAmount(amount) {
    document.querySelectorAll('.donation-amount').forEach(el => {
        el.classList.remove('selected', 'active');
    });
    
    const btn = event.target;
    btn.classList.add('selected', 'active');
    
    if (amount === 'custom') {
        const custom = prompt('Enter custom amount (USD):');
        if (custom && !isNaN(custom) && parseFloat(custom) > 0) {
            console.log('Processing custom donation:', custom);
        }
    } else {
        console.log('Processing donation:', amount);
    }
}

function copyToClipboard(type) {
    const addresses = {
        'btc': '*************************',
        'eth': '***********************'
    };
    
    const text = addresses[type];
    if (!text) return;
    
    navigator.clipboard.writeText(text).then(() => {
        alert('Address copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy:', err);
        alert('Failed to copy address. Please copy manually.');
    });
}

// ========================================
// 6. ИНИЦИАЛИЗАЦИЯ
// ========================================

function initApp() {
    // Добавляем плавность для текстовых элементов
    document.querySelectorAll('[data-translate]').forEach(el => {
        el.style.transition = 'opacity 0.2s ease';
        el.style.opacity = '1';
    });

    // Навешиваем обработчики на кнопки языка
    document.querySelectorAll('.lang-flag-btn').forEach(btn => {
        btn.addEventListener('click', handleLanguageClick);
    });

    // Навешиваем обработчики на кнопки страны (legacy)
    document.querySelectorAll('.country-btn').forEach(btn => {
        btn.addEventListener('click', handleCountryClick);
    });

    // Определяем язык по приоритету:
    // 1. Сохранённый в localStorage
    // 2. Язык браузера
    // 3. Язык по умолчанию (ru)
    const savedLang = localStorage.getItem('siteLang');
    const browserLang = navigator.language?.split('-')[0]?.toLowerCase();
    const supportedLangs = ['ru', 'en', 'zh'];
    
    const detectedLang = savedLang || 
                        (supportedLangs.includes(browserLang) ? browserLang : 'ru');
    
    // Применяем язык
    switchLanguage(detectedLang);
}

// Запускаем после загрузки DOM
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}

// Экспорт для возможного использования в других модулях
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { switchLanguage, translations, themeConfig };
}