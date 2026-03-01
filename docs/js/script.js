// Translations
const translations = {
    ru: {
        opensource: 'Open Source Проект',
        title: 'Starter Sphere',
        subtitle: 'Платформа вашего персонального AI помощника. Создайте свою летающую сферу с минимальными знаниями кода.',
        github: 'GitHub',
        donate: 'Поддержать',
        features: 'Возможности',
        feature1_title: 'AI Помощник',
        feature1_desc: 'Создайте своего персонального AI помощника с обработкой естественного языка и машинным обучением.',
        feature2_title: 'No-Code Конструктор',
        feature2_desc: 'Создавайте сложные автоматизации и рабочие процессы без написания кода.',
        feature3_title: 'Летающая Сфера',
        feature3_desc: 'Готовая архитектура для интеграции автономных дронов и IoT устройств.',
        support_title: 'Поддержите Проект',
        support_desc: 'Помогите нам создать будущее персональных AI помощников. Ваш вклад позволяет нам разрабатывать новые функции и сохранять проект open-source.',
        select_amount: 'Выберите сумму',
        custom: 'Своя',
        regional_payment: 'Региональные способы оплаты',
        select_country: 'Выберите страну выше для локальных способов оплаты',
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
        features: 'Features',
        feature1_title: 'AI Assistant',
        feature1_desc: 'Create your personal AI assistant with natural language processing and machine learning capabilities.',
        feature2_title: 'No-Code Builder',
        feature2_desc: 'Build complex automations and workflows without writing a single line of code.',
        feature3_title: 'Flying Sphere',
        feature3_desc: 'Future-ready architecture for autonomous drone and IoT device integration.',
        support_title: 'Support Our Project',
        support_desc: 'Help us build the future of personal AI assistants. Your contribution enables us to develop new features and keep the project open-source.',
        select_amount: 'Select Amount',
        custom: 'Custom',
        regional_payment: 'Regional Payment Methods',
        select_country: 'Select your country above for local payment methods',
        contribute: 'Contribute',
        contribute_desc: 'Join our community of developers and help shape the future of AI assistants',
        footer: 'Open Source Project for Personal AI Assistants'
    },
    zh: {
        opensource: '开源项目',
        title: 'Starter Sphere',
        subtitle: '您的个人AI助手平台。用最少的编码知识构建您的飞行球体。',
        github: 'GitHub',
        donate: '支持项目',
        features: '功能特点',
        feature1_title: 'AI助手',
        feature1_desc: '创建具有自然语言处理和机器学习能力的个人AI助手。',
        feature2_title: '无代码构建器',
        feature2_desc: '无需编写一行代码即可构建复杂的自动化和工作流程。',
        feature3_title: '飞行球体',
        feature3_desc: '为自主无人机和物联网设备集成提供面向未来的架构。',
        support_title: '支持我们的项目',
        support_desc: '帮助我们构建个人AI助手的未来。您的贡献使我们能够开发新功能并保持项目开源。',
        select_amount: '选择金额',
        custom: '自定义',
        regional_payment: '区域支付方式',
        select_country: '在上方选择您的国家以获取本地支付方式',
        contribute: '贡献',
        contribute_desc: '加入我们的开发者社区，帮助塑造AI助手的未来',
        footer: '个人AI助手开源项目'
    }
};

// Change Language
function changeLanguage(lang) {
    document.querySelectorAll('[data-translate]').forEach(element => {
        const key = element.getAttribute('data-translate');
        if (translations[lang] && translations[lang][key]) {
            element.textContent = translations[lang][key];
        }
    });
    localStorage.setItem('language', lang);
}

// Change Country
function changeCountry(country) {
    // Update header
    const header = document.getElementById('mainHeader');
    header.className = `country-header header-${country}`;
    
    // Update buttons
    document.querySelectorAll('.country-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-country="${country}"]`).classList.add('active');
    
    // Show regional payment
    document.querySelectorAll('.russia-payment, .china-payment, .global-payment').forEach(el => {
        el.classList.add('d-none');
    });
    
    if (country === 'russia') {
        document.querySelector('.russia-payment').classList.remove('d-none');
    } else if (country === 'china') {
        document.querySelector('.china-payment').classList.remove('d-none');
    } else {
        document.querySelector('.global-payment').classList.remove('d-none');
    }
    
    localStorage.setItem('country', country);
}

// Select Donation Amount
function selectAmount(amount) {
    document.querySelectorAll('.donation-amount').forEach(el => {
        el.classList.remove('selected');
    });
    event.target.classList.add('selected');
    
    if (amount === 'custom') {
        const custom = prompt('Enter custom amount:');
        if (custom) {
            // Process custom amount
            console.log('Custom amount:', custom);
        }
    }
}

// Copy to Clipboard
function copyToClipboard(type) {
    const addresses = {
        'btc': '*************************',
        'eth': '***********************'
    };
    
    navigator.clipboard.writeText(addresses[type]).then(() => {
        alert('Address copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy: ', err);
        alert('Failed to copy address. Please copy manually.');
    });
}

// Load saved preferences
document.addEventListener('DOMContentLoaded', function() {
    const savedLang = localStorage.getItem('language') || 'en';
    const savedCountry = localStorage.getItem('country') || 'russia';
    
    changeLanguage(savedLang);
    changeCountry(savedCountry);
});