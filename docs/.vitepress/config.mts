import { defineConfig, type DefaultTheme } from 'vitepress'

const repo = 'https://github.com/BiztechEG/laravel-easy-pdf-word'

type Labels = Record<string, string>

const en: Labels = {
  // Guide
  introduction: 'Introduction',
  installation: 'Installation',
  'quick-start': 'Quick start',
  templates: 'Ready-made templates',
  'custom-templates': 'Your own templates',
  'views-and-html': 'Blade views and HTML',
  builder: 'Building in code',
  word: 'Word files',
  output: 'Output and delivery',
  'page-settings': 'Page settings',
  engines: 'PDF engines',
  arabic: 'Arabic support',
  fonts: 'Fonts',
  images: 'Images',
  preview: 'Preview page',
  commands: 'Artisan commands',
  testing: 'Testing your app',
  security: 'Security',
  configuration: 'Configuration',
  troubleshooting: 'Troubleshooting',
  // Templates
  'invoice:t': 'Tax invoice',
  'eg-invoice:t': 'Egyptian e-invoice',
  'credit-note:t': 'Credit and debit note',
  'quotation:t': 'Price quotation',
  'purchase-order:t': 'Purchase order',
  'delivery-note:t': 'Delivery note',
  'receipt:t': 'Receipt and payment voucher',
  'payslip:t': 'Payslip',
  'contract:t': 'Contract',
  'certificate:t': 'Certificate',
  'letter:t': 'Official letter',
  'report:t': 'Table report',
  // Recipes
  'email-invoice': 'Email an invoice',
  'zatca-invoice': 'Saudi ZATCA invoice',
  'egypt-e-invoice': 'Print an ETA e-invoice',
  'payslips-zip': 'Monthly payslips in one ZIP',
  'sales-report': 'Sales report from a query',
  certificates: 'Course certificates in bulk',
  'word-designed-template': 'A template designed in Word',
  'multi-tenant-branding': 'Branding per customer',
  contract: 'Contracts from your data',
  'price-list-builder': 'A price list built in code',
  'controller-responses': 'Download, preview or store',
  'testing-documents': 'Testing document features',
  // Reference
  api: 'Doc API',
  'template-helpers': 'Template helpers',
}

const ar: Labels = {
  introduction: 'مقدمة',
  installation: 'التثبيت',
  'quick-start': 'البداية السريعة',
  templates: 'القوالب الجاهزة',
  'custom-templates': 'قوالبك الخاصة',
  'views-and-html': 'ملفات Blade و HTML',
  builder: 'بناء المستند بالكود',
  word: 'ملفات Word',
  output: 'الإخراج والتسليم',
  'page-settings': 'إعدادات الصفحة',
  engines: 'محركات PDF',
  arabic: 'دعم اللغة العربية',
  fonts: 'الخطوط',
  images: 'الصور',
  preview: 'صفحة المعاينة',
  commands: 'أوامر Artisan',
  testing: 'اختبار تطبيقك',
  security: 'الأمان',
  configuration: 'الإعدادات',
  troubleshooting: 'حل المشكلات',
  'invoice:t': 'الفاتورة الضريبية',
  'eg-invoice:t': 'الفاتورة الإلكترونية المصرية',
  'credit-note:t': 'إشعار دائن ومدين',
  'quotation:t': 'عرض سعر',
  'purchase-order:t': 'أمر شراء',
  'delivery-note:t': 'إذن تسليم',
  'receipt:t': 'سند قبض وسند صرف',
  'payslip:t': 'قسيمة راتب',
  'contract:t': 'عقد',
  'certificate:t': 'شهادة',
  'letter:t': 'خطاب رسمي',
  'report:t': 'تقرير جدولي',
  'email-invoice': 'إرسال فاتورة بالبريد',
  'zatca-invoice': 'فاتورة هيئة الزكاة السعودية',
  'egypt-e-invoice': 'طباعة الفاتورة الإلكترونية المصرية',
  'payslips-zip': 'قسائم الرواتب الشهرية في ملف ZIP',
  'sales-report': 'تقرير مبيعات من قاعدة البيانات',
  certificates: 'شهادات دورة تدريبية دفعة واحدة',
  'word-designed-template': 'قالب مصمم في Word',
  'multi-tenant-branding': 'هوية مختلفة لكل عميل',
  contract: 'عقود من بياناتك',
  'price-list-builder': 'قائمة أسعار مبنية بالكود',
  'controller-responses': 'تنزيل أو عرض أو حفظ',
  'testing-documents': 'اختبار ميزات المستندات',
  api: 'واجهة Doc',
  'template-helpers': 'أدوات القوالب',
}

function sidebar(prefix: string, l: Labels, sections: Labels): DefaultTheme.Sidebar {
  const item = (dir: string, page: string, key = page) => ({ text: l[key], link: `${prefix}/${dir}/${page}` })

  const guide: DefaultTheme.SidebarItem[] = [
    { text: sections.start, items: ['introduction', 'installation', 'quick-start'].map(p => item('guide', p)) },
    { text: sections.making, items: ['templates', 'custom-templates', 'views-and-html', 'builder', 'word'].map(p => item('guide', p)) },
    { text: sections.output, items: ['output', 'page-settings', 'engines'].map(p => item('guide', p)) },
    { text: sections.arabic, items: ['arabic', 'fonts', 'images'].map(p => item('guide', p)) },
    { text: sections.tools, items: ['preview', 'commands', 'testing'].map(p => item('guide', p)) },
    { text: sections.production, items: ['security', 'configuration', 'troubleshooting'].map(p => item('guide', p)) },
    { text: sections.reference, items: ['api', 'template-helpers'].map(p => item('reference', p)) },
  ]

  return {
    [`${prefix}/guide/`]: guide,
    [`${prefix}/reference/`]: guide,
    [`${prefix}/templates/`]: [
      { text: sections.templates, items: [{ text: sections.overview, link: `${prefix}/templates/` }] },
      { text: sections.sales, items: ['invoice', 'eg-invoice', 'credit-note', 'quotation', 'receipt'].map(p => item('templates', p, `${p}:t`)) },
      { text: sections.supply, items: ['purchase-order', 'delivery-note'].map(p => item('templates', p, `${p}:t`)) },
      { text: sections.hr, items: ['payslip', 'contract', 'certificate', 'letter', 'report'].map(p => item('templates', p, `${p}:t`)) },
    ],
    [`${prefix}/recipes/`]: [
      { text: sections.recipes, items: [{ text: sections.overview, link: `${prefix}/recipes/` }] },
      { text: sections.billing, items: ['email-invoice', 'zatca-invoice', 'egypt-e-invoice', 'controller-responses'].map(p => item('recipes', p)) },
      { text: sections.bulk, items: ['payslips-zip', 'certificates', 'sales-report'].map(p => item('recipes', p)) },
      { text: sections.custom, items: ['word-designed-template', 'multi-tenant-branding', 'contract', 'price-list-builder'].map(p => item('recipes', p)) },
      { text: sections.quality, items: ['testing-documents'].map(p => item('recipes', p)) },
    ],
  }
}

function nav(prefix: string, n: Labels): DefaultTheme.NavItem[] {
  return [
    { text: n.guide, link: `${prefix}/guide/introduction`, activeMatch: `^${prefix}/guide/` },
    { text: n.templates, link: `${prefix}/templates/`, activeMatch: `^${prefix}/templates/` },
    { text: n.recipes, link: `${prefix}/recipes/`, activeMatch: `^${prefix}/recipes/` },
    { text: n.reference, link: `${prefix}/reference/api`, activeMatch: `^${prefix}/reference/` },
    {
      text: 'v1.2',
      items: [
        { text: n.changelog, link: `${repo}/blob/main/CHANGELOG.md` },
        { text: 'Packagist', link: 'https://packagist.org/packages/biztecheg/laravel-easy-pdf-word' },
      ],
    },
  ]
}

const enSections: Labels = {
  start: 'Getting started', making: 'Making documents', output: 'Output', arabic: 'Arabic and languages',
  tools: 'Tools', production: 'Production', reference: 'Reference', templates: 'Templates', overview: 'Overview',
  sales: 'Sales and billing', supply: 'Purchasing and delivery', hr: 'HR and office', recipes: 'Recipes',
  billing: 'Invoices and billing', bulk: 'Bulk and reports', custom: 'Custom documents', quality: 'Quality',
}

const arSections: Labels = {
  start: 'البداية', making: 'إنشاء المستندات', output: 'الإخراج', arabic: 'العربية واللغات',
  tools: 'أدوات', production: 'بيئة الإنتاج', reference: 'المرجع', templates: 'القوالب', overview: 'نظرة عامة',
  sales: 'المبيعات والفواتير', supply: 'المشتريات والتسليم', hr: 'الموارد البشرية والمكاتبات', recipes: 'حالات الاستخدام',
  billing: 'الفواتير', bulk: 'الدفعات والتقارير', custom: 'مستندات مخصصة', quality: 'الجودة',
}

export default defineConfig({
  title: 'Laravel Easy PDF & Word',
  description: 'Generate PDF and Word documents from Laravel in any language, with first-class Arabic support and ready-made templates.',
  lastUpdated: true,
  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/logo.svg' }],
    ['meta', { name: 'theme-color', content: '#0F766E' }],
  ],
  markdown: {
    theme: { light: 'github-light', dark: 'github-dark' },
    config(md) {
      // Blade's {{ }} in inline code is text, not a Vue expression.
      const codeInline = md.renderer.rules.code_inline!
      md.renderer.rules.code_inline = (...args) => codeInline(...args).replace(/^<code/, '<code v-pre')
    },
  },
  themeConfig: {
    logo: '/logo.svg',
    socialLinks: [{ icon: 'github', link: repo }],
    search: {
      provider: 'local',
      options: {
        locales: {
          ar: {
            translations: {
              button: { buttonText: 'بحث', buttonAriaLabel: 'بحث' },
              modal: {
                displayDetails: 'عرض التفاصيل',
                resetButtonTitle: 'مسح البحث',
                backButtonTitle: 'إغلاق البحث',
                noResultsText: 'لا توجد نتائج لـ',
                footer: { selectText: 'فتح', navigateText: 'تنقل', closeText: 'إغلاق' },
              },
            },
          },
        },
      },
    },
  },
  locales: {
    root: {
      label: 'English',
      lang: 'en',
      themeConfig: {
        nav: nav('', { guide: 'Guide', templates: 'Templates', recipes: 'Recipes', reference: 'Reference', changelog: 'Changelog' }),
        sidebar: sidebar('', en, enSections),
        editLink: { pattern: `${repo}/edit/main/docs/:path`, text: 'Edit this page on GitHub' },
        footer: {
          message: 'Released under the MIT License.',
          copyright: 'Copyright © BizTech',
        },
      },
    },
    ar: {
      label: 'العربية',
      lang: 'ar',
      dir: 'rtl',
      title: 'Laravel Easy PDF & Word',
      description: 'مكتبة Laravel لإنشاء ملفات PDF و Word بأي لغة، مع دعم كامل للعربية وقوالب جاهزة.',
      themeConfig: {
        nav: nav('/ar', { guide: 'الدليل', templates: 'القوالب', recipes: 'حالات الاستخدام', reference: 'المرجع', changelog: 'سجل التغييرات' }),
        sidebar: sidebar('/ar', ar, arSections),
        editLink: { pattern: `${repo}/edit/main/docs/:path`, text: 'عدّل هذه الصفحة على GitHub' },
        outline: { label: 'في هذه الصفحة', level: [2, 3] },
        docFooter: { prev: 'السابق', next: 'التالي' },
        lastUpdated: { text: 'آخر تحديث' },
        returnToTopLabel: 'العودة للأعلى',
        sidebarMenuLabel: 'القائمة',
        darkModeSwitchLabel: 'المظهر',
        lightModeSwitchTitle: 'الوضع الفاتح',
        darkModeSwitchTitle: 'الوضع الداكن',
        langMenuLabel: 'اللغة',
        notFound: {
          title: 'الصفحة غير موجودة',
          quote: 'ربما نُقلت هذه الصفحة أو حُذفت.',
          linkText: 'العودة للرئيسية',
        },
        footer: {
          message: 'منشورة برخصة MIT.',
          copyright: 'حقوق النشر © BizTech',
        },
      },
    },
  },
})
