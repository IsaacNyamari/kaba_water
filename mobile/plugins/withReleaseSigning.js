/**
 * Signs release builds with our own keystore instead of the debug key, so
 * every APK we publish can update the previous one in place.
 *
 * Reads credentials/keystore.properties (never committed):
 *   storeFile=hs-water-billing.keystore   (relative to credentials/)
 *   storePassword=...
 *   keyAlias=hs-water-billing
 *   keyPassword=...
 */
const { withAppBuildGradle } = require('expo/config-plugins');

const MARKER = '// hs-release-signing';

module.exports = function withReleaseSigning(config) {
  return withAppBuildGradle(config, (cfg) => {
    let gradle = cfg.modResults.contents;
    if (gradle.includes(MARKER)) return cfg;

    const loader = `
${MARKER}
def hsSigningProps = new Properties()
def hsSigningFile = rootProject.file('../credentials/keystore.properties')
if (hsSigningFile.exists()) { hsSigningFile.withInputStream { hsSigningProps.load(it) } }
`;
    gradle = gradle.replace(/^android\s*\{/m, `${loader}\nandroid {`);

    gradle = gradle.replace(
      /signingConfigs\s*\{/,
      `signingConfigs {
        release {
            if (hsSigningFile.exists()) {
                storeFile rootProject.file('../credentials/' + hsSigningProps['storeFile'])
                storePassword hsSigningProps['storePassword']
                keyAlias hsSigningProps['keyAlias']
                keyPassword hsSigningProps['keyPassword']
            }
        }`,
    );

    // The release build type uses the release key when it is present.
    gradle = gradle.replace(
      /(buildTypes\s*\{[\s\S]*?release\s*\{[\s\S]*?)signingConfig\s+signingConfigs\.debug/,
      `$1signingConfig hsSigningFile.exists() ? signingConfigs.release : signingConfigs.debug`,
    );

    cfg.modResults.contents = gradle;
    return cfg;
  });
};
