import { useEffect, useState } from 'react';

/** Tiny hash router — avoids a routing dependency for the shell. */
export function useHashRoute(defaultRoute = 'overview'): string {
  const read = () => window.location.hash.replace(/^#\/?/, '') || defaultRoute;
  const [route, setRoute] = useState<string>(read);

  useEffect(() => {
    const onChange = () => setRoute(read());
    window.addEventListener('hashchange', onChange);
    return () => window.removeEventListener('hashchange', onChange);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return route;
}
