import type { ReactNode } from 'react';
import type { PublicBranding } from './AuthScreen';
import { BarChart3, Clock3, Gauge, Moon, ShieldCheck, Sun, UsersRound } from 'lucide-react';
import { useTheme } from '../../theme';
import { IconButton, Tooltip, Image } from '../../design-system';
import { useLocalization } from '../../i18n/LocalizationContext';
import LanguageSwitcher from '../../i18n/LanguageSwitcher';
/** Handles the auth layout operation for the WorkIntel client. */ export default function AuthLayout({ children, branding }: {
    children: ReactNode;
    branding: PublicBranding;
}) {
    const { theme, toggleTheme } = useTheme();
    const { t } = useLocalization();
    return <div className="auth-shell">
    <section className="auth-showcase">
      <div className="auth-showcase__brand"><span className="auth-showcase__logo">{branding.logo_url ? <Image src={branding.logo_url} alt="" width={22} height={22} objectFit="contain"/> : <Gauge size={18}/>}</span><span>{branding.product_name || 'WorkIntel'}</span></div>
      <div className="auth-showcase__content">
        <div className="auth-showcase__eyebrow">{t('auth.eyebrow')}</div>
        <h1>{branding.login_title || t('auth.headline')}</h1>
        <p>{branding.login_subtitle || t('auth.subtitle')}</p>
        <div className="auth-showcase__metrics">
          <div><UsersRound size={16}/><strong>17</strong><span>working now</span></div>
          <div><Clock3 size={16}/><strong>142h</strong><span>tracked today</span></div>
          <div><BarChart3 size={16}/><strong>86%</strong><span>attendance</span></div>
        </div>
      </div>
      {!branding.hide_powered_by && <div className="auth-showcase__foot"><ShieldCheck size={15}/><span>Privacy controls, role-based access and clear employee visibility are built into the product model.</span></div>}
    </section>

    <main className="auth-main">
      <div className="auth-theme-toggle"><LanguageSwitcher compact/><Tooltip content={theme === 'dark' ? 'Use light mode' : 'Use dark mode'}><IconButton variant="outline" onClick={toggleTheme} aria-label="Toggle theme">{theme === 'dark' ? <Sun size={15}/> : <Moon size={15}/>}</IconButton></Tooltip></div>
      <div className="auth-panel">{children}</div>
    </main>
  </div>;
}
