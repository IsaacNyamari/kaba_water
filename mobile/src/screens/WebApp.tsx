import { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, BackHandler, Linking, Pressable, StyleSheet, Text, View } from 'react-native';
import { WebView as RNWebView } from 'react-native-webview';
import type { ComponentType, Ref } from 'react';
import type { ShouldStartLoadRequest, WebViewNavigation } from 'react-native-webview/lib/WebViewTypes';
import type { WebViewProps } from 'react-native-webview/lib/WebView';
import { HOST, colors, isSignedOutUrl } from '../config';

type WebViewHandle = { goBack(): void; reload(): void };

/** The package types WebView without its ref; React 19 needs it declared. */
const WebView = RNWebView as unknown as ComponentType<WebViewProps & { ref?: Ref<WebViewHandle> }>;

/**
 * The full HS Water Billing web app inside the app, signed in with the
 * same session cookie as the website. Links outside the system (phone,
 * email, WhatsApp, other sites) open in the phone's own apps.
 */
export default function WebApp({ startUrl, onSignedOut }: { startUrl: string; onSignedOut: () => void }) {
  const webview = useRef<WebViewHandle>(null);
  const [canGoBack, setCanGoBack] = useState(false);
  const [failed, setFailed] = useState(false);

  // Android back button goes back inside the web app first.
  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      if (canGoBack) {
        webview.current?.goBack();
        return true;
      }
      return false;
    });
    return () => sub.remove();
  }, [canGoBack]);

  const onNavigation = useCallback(
    (nav: WebViewNavigation) => {
      setCanGoBack(nav.canGoBack);
      if (!nav.loading && isSignedOutUrl(nav.url)) {
        onSignedOut();
      }
    },
    [onSignedOut],
  );

  return (
    <View style={{ flex: 1, backgroundColor: colors.white }}>
      {failed ? (
        <View style={styles.center}>
          <Text style={styles.title}>You are offline</Text>
          <Text style={styles.text}>HS Water Billing needs an internet connection.</Text>
          <Pressable style={styles.button} onPress={() => { setFailed(false); webview.current?.reload(); }}>
            <Text style={styles.buttonText}>Try again</Text>
          </Pressable>
        </View>
      ) : null}
      <WebView
        ref={webview}
        source={{ uri: startUrl }}
        style={failed ? { height: 0, flex: 0 } : { flex: 1 }}
        applicationNameForUserAgent="HSWaterBillingApp/1.0"
        sharedCookiesEnabled
        thirdPartyCookiesEnabled
        domStorageEnabled
        javaScriptEnabled
        allowFileAccess
        allowsBackForwardNavigationGestures
        pullToRefreshEnabled
        mediaCapturePermissionGrantType="grant"
        setSupportMultipleWindows={false}
        startInLoadingState
        renderLoading={() => (
          <View style={[StyleSheet.absoluteFill, styles.center]}>
            <ActivityIndicator size="large" color={colors.brandDark} />
          </View>
        )}
        onNavigationStateChange={onNavigation}
        onShouldStartLoadWithRequest={(request: ShouldStartLoadRequest) => {
          const { url } = request;
          if (url.startsWith('http://') || url.startsWith('https://')) {
            try {
              if (new URL(url).hostname === HOST) return true;
            } catch {
              return false;
            }
          }
          if (url.startsWith('about:') || url.startsWith('blob:') || url.startsWith('data:')) return true;
          // tel:, mailto:, whatsapp:, other websites → the phone's own apps.
          Linking.openURL(url).catch(() => {});
          return false;
        }}
        onError={() => setFailed(true)}
        onHttpError={() => {}}
        onRenderProcessGone={() => webview.current?.reload()}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24, backgroundColor: colors.white },
  title: { fontSize: 20, fontWeight: '700', color: colors.text },
  text: { fontSize: 15, color: colors.muted, marginTop: 6, textAlign: 'center' },
  button: { backgroundColor: colors.brandDark, borderRadius: 12, paddingVertical: 12, paddingHorizontal: 24, marginTop: 20 },
  buttonText: { color: colors.white, fontWeight: '700' },
});
