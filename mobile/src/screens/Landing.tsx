import { useEffect, useState } from 'react';
import { Image, Linking, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import Constants from 'expo-constants';
import { BASE_URL, colors } from '../config';

const FEATURES = [
  { title: 'Meter readings', text: 'Scan a meter with the camera — the reading is filled in for you.' },
  { title: 'Bills & payments', text: 'Invoices go out by SMS; customers pay with M-Pesa.' },
  { title: 'Customer portal', text: 'Customers see their usage, bills and receipts any time.' },
];

/** Older first: "1.2.10" > "1.2.9". */
function isNewer(latest: string, current: string): boolean {
  const a = latest.split('.').map(Number);
  const b = current.split('.').map(Number);
  for (let i = 0; i < Math.max(a.length, b.length); i++) {
    if ((a[i] ?? 0) !== (b[i] ?? 0)) return (a[i] ?? 0) > (b[i] ?? 0);
  }
  return false;
}

export default function Landing({ onSignIn }: { onSignIn: () => void }) {
  const [update, setUpdate] = useState<{ version: string; url: string } | null>(null);

  useEffect(() => {
    const current = Constants.expoConfig?.version ?? '0.0.0';
    fetch(`${BASE_URL}/api/mobile/version`)
      .then((r) => r.json())
      .then((v) => {
        if (v?.latest_version && v?.download_url && isNewer(String(v.latest_version), current)) {
          setUpdate({ version: String(v.latest_version), url: String(v.download_url) });
        }
      })
      .catch(() => {});
  }, []);

  return (
    <ScrollView contentContainerStyle={styles.page} bounces={false}>
      <View style={styles.hero}>
        <View style={styles.logoCard}>
          <Image source={require('../../assets/logo.png')} style={styles.logo} resizeMode="contain" />
        </View>
        <Text style={styles.title}>HS Water Billing</Text>
        <Text style={styles.subtitle}>Water billing made simple — from the meter reading to the invoice to the M-Pesa payment.</Text>
      </View>

      {update && (
        <Pressable style={styles.update} onPress={() => Linking.openURL(update.url)}>
          <Text style={styles.updateText}>Version {update.version} is available — tap to download.</Text>
        </Pressable>
      )}

      <View style={styles.features}>
        {FEATURES.map((f) => (
          <View key={f.title} style={styles.feature}>
            <View style={styles.dot} />
            <View style={{ flex: 1 }}>
              <Text style={styles.featureTitle}>{f.title}</Text>
              <Text style={styles.featureText}>{f.text}</Text>
            </View>
          </View>
        ))}
      </View>

      <Pressable style={({ pressed }) => [styles.button, pressed && { opacity: 0.85 }]} onPress={onSignIn}>
        <Text style={styles.buttonText}>Sign in</Text>
      </Pressable>
      <Text style={styles.footer}>By Human Solutions · humsols.com</Text>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  page: { flexGrow: 1, backgroundColor: colors.navy, padding: 24, paddingTop: 56, justifyContent: 'space-between' },
  hero: { alignItems: 'center' },
  logoCard: { backgroundColor: colors.white, borderRadius: 24, paddingVertical: 18, paddingHorizontal: 28, marginBottom: 24 },
  logo: { width: 200, height: 64 },
  title: { color: colors.white, fontSize: 28, fontWeight: '700', textAlign: 'center' },
  subtitle: { color: '#cbd5e1', fontSize: 15, lineHeight: 22, textAlign: 'center', marginTop: 10, maxWidth: 320 },
  update: { backgroundColor: colors.brandLight, borderRadius: 12, padding: 12, marginTop: 20 },
  updateText: { color: colors.brandDark, fontWeight: '600', textAlign: 'center' },
  features: { marginVertical: 32, gap: 16 },
  feature: { flexDirection: 'row', gap: 12, backgroundColor: colors.navySoft, borderRadius: 16, padding: 16 },
  dot: { width: 10, height: 10, borderRadius: 5, backgroundColor: colors.brand, marginTop: 6 },
  featureTitle: { color: colors.white, fontSize: 16, fontWeight: '600' },
  featureText: { color: '#94a3b8', fontSize: 14, lineHeight: 20, marginTop: 2 },
  button: { backgroundColor: colors.brand, borderRadius: 14, paddingVertical: 16, alignItems: 'center' },
  buttonText: { color: colors.white, fontSize: 17, fontWeight: '700' },
  footer: { color: '#64748b', fontSize: 12, textAlign: 'center', marginTop: 16 },
});
