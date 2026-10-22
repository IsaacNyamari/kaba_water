import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';
import * as SecureStore from 'expo-secure-store';
import { BASE_URL, colors } from './src/config';
import Landing from './src/screens/Landing';
import Login from './src/screens/Login';
import WebApp from './src/screens/WebApp';

type Screen = { name: 'loading' } | { name: 'landing' } | { name: 'login' } | { name: 'app'; url: string };

const SIGNED_IN_KEY = 'hs-signed-in';

/**
 * Landing → Login → the web app. A signed-in user opens straight into the
 * web app next time (the session cookie is remembered); if the server says
 * they are signed out, the app shows its own login again.
 */
export default function App() {
  const [screen, setScreen] = useState<Screen>({ name: 'loading' });

  useEffect(() => {
    SecureStore.getItemAsync(SIGNED_IN_KEY)
      .then((value) => setScreen(value === '1' ? { name: 'app', url: `${BASE_URL}/dashboard` } : { name: 'landing' }))
      .catch(() => setScreen({ name: 'landing' }));
  }, []);

  const signedIn = useCallback((sessionUrl: string) => {
    SecureStore.setItemAsync(SIGNED_IN_KEY, '1').catch(() => {});
    setScreen({ name: 'app', url: sessionUrl });
  }, []);

  const signedOut = useCallback(() => {
    SecureStore.deleteItemAsync(SIGNED_IN_KEY).catch(() => {});
    setScreen({ name: 'login' });
  }, []);

  const dark = screen.name === 'landing' || screen.name === 'loading';

  return (
    <SafeAreaProvider>
      <StatusBar style={dark ? 'light' : 'dark'} />
      <SafeAreaView style={{ flex: 1, backgroundColor: dark ? colors.navy : colors.white }} edges={['top', 'bottom']}>
        {screen.name === 'loading' && (
          <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}>
            <ActivityIndicator color={colors.brand} size="large" />
          </View>
        )}
        {screen.name === 'landing' && <Landing onSignIn={() => setScreen({ name: 'login' })} />}
        {screen.name === 'login' && <Login onBack={() => setScreen({ name: 'landing' })} onSignedIn={signedIn} />}
        {screen.name === 'app' && <WebApp key={screen.url} startUrl={screen.url} onSignedOut={signedOut} />}
      </SafeAreaView>
    </SafeAreaProvider>
  );
}
