export const copy = {
    en: {
        title: 'Admin access',
        intro: 'Choose who can manage Elancer.',
        search: 'Search name or email',
        find: 'Search',
        account: 'Account',
        access: 'Access',
        member: 'Member',
        admin: 'Admin',
        superAdmin: 'Super admin',
        protected: 'Protected',
        manage: 'Manage access',
        empty: 'No accounts match your search.',
        previous: 'Previous',
        next: 'Next',
        updated: 'Admin access updated.',
        back: 'Back to admin access',
        grant: 'Grant admin access',
        revoke: 'Remove admin access',
        grantHelp:
            'This account must be active and email-verified. Admin tools also require two-factor authentication.',
        revokeHelp:
            'This person will keep their account and lose access to admin tools.',
        reason: 'Reason for this change',
        reasonHelp:
            'Record why you are changing access. This is saved in the access history.',
        saving: 'Saving…',
        failed: 'Could not save this change. Please try again.',
        verified: 'Email verified',
        unverified: 'Email not verified',
        active: 'Active',
        suspended: 'Suspended',
        deactivated: 'Deactivated',
    },
    ar: {
        title: 'صلاحيات الإدارة',
        intro: 'اختر من يمكنه إدارة إيلانسر.',
        search: 'ابحث بالاسم أو البريد',
        find: 'بحث',
        account: 'الحساب',
        access: 'الصلاحية',
        member: 'عضو',
        admin: 'مدير',
        superAdmin: 'مشرف عام',
        protected: 'محمي',
        manage: 'إدارة الصلاحية',
        empty: 'لا توجد حسابات مطابقة للبحث.',
        previous: 'السابق',
        next: 'التالي',
        updated: 'تم تحديث صلاحية الإدارة.',
        back: 'العودة إلى صلاحيات الإدارة',
        grant: 'منح صلاحية الإدارة',
        revoke: 'إزالة صلاحية الإدارة',
        grantHelp:
            'يجب أن يكون الحساب نشطاً وبريده مؤكداً. تتطلب أدوات الإدارة المصادقة الثنائية أيضاً.',
        revokeHelp: 'سيحتفظ هذا الشخص بحسابه ويفقد الوصول إلى أدوات الإدارة.',
        reason: 'سبب التغيير',
        reasonHelp: 'اكتب سبب تغيير الصلاحية. يُحفظ السبب في سجل الصلاحيات.',
        saving: 'جارٍ الحفظ…',
        failed: 'تعذر حفظ التغيير. حاول مجدداً.',
        verified: 'البريد مؤكد',
        unverified: 'البريد غير مؤكد',
        active: 'نشط',
        suspended: 'موقوف',
        deactivated: 'معطل',
    },
};
export type Account = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    is_super_admin?: boolean;
    status: 'active' | 'suspended' | 'deactivated';
    email_verified_at: string | null;
};
