import { useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Linking,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { BASE_URL, colors } from '../config';

type Mode = 'email' | 'phone';

/**
 * Native sign-in. Staff use email + password; customers use their phone,
 * meter number and PIN (the same as the website). On success the server
 * returns a one-time link that opens a normal web session in the app.
 */
export default function Login({ onBack, onSignedIn }: { onBack: () => void; onSignedIn: (sessionUrl: string) => void }) {
  const [mode, setMode] = useState<Mode>('email');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [meter, setMeter] = useState('');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit() {
    setError(null);
    setBusy(true);
    try {
      const body = mode === 'phone' ? { mode, phone, meter, password } : { mode, email, password };
      const response = await fetch(`${BASE_URL}/api/mobile/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body),
      });
      const data = await response.json().catch(() => ({}));

      if (response.ok && data.session_url) {
        onSignedIn(String(data.session_url));
        return;
      }
      setError(data.message ?? 'Sign-in failed. Please try again.');
    } catch {
      setError('Cannot reach the server. Check your internet connection.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: colors.white }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={styles.page} keyboardShouldPersistTaps="handled">
        <Pressable onPress={onBack} hitSlop={12}>
          <Text style={styles.back}>‹ Back</Text>
        </Pressable>

        <Text style={styles.title}>Sign in</Text>
        <Text style={styles.subtitle}>Welcome back to HS Water Billing.</Text>

        <View style={styles.tabs}>
          {(['email', 'phone'] as Mode[]).map((m) => (
            <Pressable key={m} style={[styles.tab, mode === m && styles.tabActive]} onPress={() => { setMode(m); setError(null); }}>
              <Text style={[styles.tabText, mode === m && styles.tabTextActive]}>{m === 'email' ? 'Staff' : 'Customer'}</Text>
            </Pressable>
          ))}
        </View>

        {mode === 'email' ? (
          <Field label="Email" value={email} onChangeText={setEmail} keyboardType="email-address" autoComplete="email" placeholder="you@company.com" />
        ) : (
          <>
            <Field label="Phone number" value={phone} onChangeText={setPhone} keyboardType="phone-pad" autoComplete="tel" placeholder="0712 345 678" />
            <Field label="Meter number" value={meter} onChangeText={setMeter} placeholder="As shown on your bill" />
          </>
        )}
        <Field
          label={mode === 'phone' ? 'PIN or password' : 'Password'}
          value={password}
          onChangeText={setPassword}
          secureTextEntry
          autoComplete="password"
          keyboardType={mode === 'phone' ? 'number-pad' : 'default'}
        />

        {error && <Text style={styles.error}>{error}</Text>}

        <Pressable style={({ pressed }) => [styles.button, (busy || pressed) && { opacity: 0.8 }]} onPress={submit} disabled={busy}>
          {busy ? <ActivityIndicator color={colors.white} /> : <Text style={styles.buttonText}>Sign in</Text>}
        </Pressable>

        <Pressable onPress={() => Linking.openURL(`${BASE_URL}/forgot-password`)}>
          <Text style={styles.link}>Forgot your password?</Text>
        </Pressable>
        {mode === 'phone' && <Text style={styles.hint}>Forgot your PIN? Ask your water provider to set a new one.</Text>}
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

function Field({ label, ...props }: { label: string } & React.ComponentProps<typeof TextInput>) {
  return (
    <View style={{ marginBottom: 14 }}>
      <Text style={styles.label}>{label}</Text>
      <TextInput style={styles.input} autoCapitalize="none" autoCorrect={false} placeholderTextColor="#94a3b8" {...props} />
    </View>
  );
}

const styles = StyleSheet.create({
  page: { flexGrow: 1, padding: 24, paddingTop: 56 },
  back: { color: colors.brandDark, fontSize: 16, fontWeight: '600', marginBottom: 24 },
  title: { fontSize: 28, fontWeight: '700', color: colors.text },
  subtitle: { fontSize: 15, color: colors.muted, marginTop: 4, marginBottom: 24 },
  tabs: { flexDirection: 'row', backgroundColor: '#f1f5f9', borderRadius: 12, padding: 4, marginBottom: 20 },
  tab: { flex: 1, paddingVertical: 10, borderRadius: 9, alignItems: 'center' },
  tabActive: { backgroundColor: colors.white, elevation: 1 },
  tabText: { color: colors.muted, fontWeight: '600' },
  tabTextActive: { color: colors.text },
  label: { fontSize: 14, fontWeight: '600', color: colors.text, marginBottom: 6 },
  input: { borderWidth: 1, borderColor: colors.border, borderRadius: 12, paddingHorizontal: 14, paddingVertical: 12, fontSize: 16, color: colors.text },
  error: { color: colors.danger, marginBottom: 12, fontSize: 14 },
  button: { backgroundColor: colors.brandDark, borderRadius: 14, paddingVertical: 16, alignItems: 'center', marginTop: 6 },
  buttonText: { color: colors.white, fontSize: 17, fontWeight: '700' },
  link: { color: colors.brandDark, textAlign: 'center', marginTop: 20, fontWeight: '600' },
  hint: { color: colors.muted, textAlign: 'center', marginTop: 8, fontSize: 13 },
});
