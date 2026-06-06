import type { ReactNode } from 'react';
import './Sidebar.css';

interface SidebarProps {
  children: ReactNode;
  width?: string;
  className?: string;
}

/**
 * Generic Sidebar component.
 *
 * Used as the foundation for the POS fullscreen sidebar layout.
 * Reserved for future POS navigation.
 *
 * In wp-admin this component is NOT mounted as main navigation.
 * It is shown only inside the Design System demo in an isolated container.
 */
function Sidebar({ children, width = '256px', className = '' }: SidebarProps) {
  return (
    <aside
      className={`mx-ui-sidebar ${className}`.trim()}
      style={{ width }}
      aria-label="POS sidebar"
    >
      {children}
    </aside>
  );
}

export default Sidebar;
