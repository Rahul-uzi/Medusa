import React, { useState } from 'react';
import {
  StyleSheet,
  Text,
  View,
  TextInput,
  TouchableOpacity,
  Image,
  SafeAreaView,
  StatusBar,
  KeyboardAvoidingView,
  Platform,
  Alert,
} from 'react-native';

export default function LoginScreen({ navigation }) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [rememberMe, setRememberMe] = useState(false);

  const handleLogin = async () => {
    if (!email || !password) {
      Alert.alert('Error', 'Please fill in all fields.');
      return;
    }

    const data = {
      email,
      password,
      remember: rememberMe,
    };

    try {
      // Retaining identical API communication logic
      const response = await fetch('http://your-server-ip/api/login.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
      });
      const result = await response.json();
      if (result.success) {
        Alert.alert('Success', 'Login successful');
        // Navigate to your main application screen
        // navigation.replace('Home');
      } else {
        Alert.alert('Error', result.message || 'Login failed.');
      }
    } catch (error) {
      console.error(error);
      Alert.alert('Error', 'Network error or server unavailable.');
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar barStyle="light-content" backgroundColor="#000000" />
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        style={styles.keyboardView}
      >
        <View style={styles.card}>
          {/* Brand Logo Header */}
          <View style={styles.brandContainer}>
            <Image
              source={require('./assets/images/medusaa2(onlylogo).png')}
              style={styles.logo}
              resizeMode="contain"
            />
          </View>

          {/* Email Input */}
          <View style={styles.inputWrapper}>
            <Text style={styles.icon}>@</Text>
            <TextInput
              style={styles.input}
              placeholder="E-MAIL"
              placeholderTextColor="rgba(255, 255, 255, 0.4)"
              keyboardType="email-address"
              autoCapitalize="none"
              value={email}
              onChangeText={setEmail}
            />
          </View>

          {/* Password Input */}
          <View style={styles.inputWrapper}>
            <Text style={styles.icon}>🔒</Text>
            <TextInput
              style={styles.input}
              placeholder="PASSWORD"
              placeholderTextColor="rgba(255, 255, 255, 0.4)"
              secureTextEntry
              autoCapitalize="none"
              value={password}
              onChangeText={setPassword}
            />
          </View>

          {/* Remember Me & Forgot Password Options Row */}
          <View style={styles.optionsRow}>
            <TouchableOpacity
              style={styles.rememberBtn}
              onPress={() => setRememberMe(!rememberMe)}
            >
              <View style={[styles.checkbox, rememberMe && styles.checkboxChecked]}>
                {rememberMe && <Text style={styles.checkMark}>✓</Text>}
              </View>
              <Text style={styles.rememberText}>Remember Me</Text>
            </TouchableOpacity>

            <TouchableOpacity onPress={() => Alert.alert('Notice', 'Password reset sent.')}>
              <Text style={styles.forgotText}>Forgot Password</Text>
            </TouchableOpacity>
          </View>

          {/* Log In Button - Luxury Gold */}
          <TouchableOpacity style={styles.loginBtn} onPress={handleLogin}>
            <Text style={styles.loginBtnText}>Log in</Text>
          </TouchableOpacity>

          {/* Sign Up Link */}
          <View style={styles.signUpRow}>
            <Text style={styles.signUpText}>Not a Member?</Text>
            <TouchableOpacity onPress={() => Alert.alert('Redirect', 'Navigate to SignUp')}>
              <Text style={styles.signUpLink}> Join Now</Text>
            </TouchableOpacity>
          </View>
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#000000', // Minimal matte black background
    justifyContent: 'center',
    alignItems: 'center',
  },
  keyboardView: {
    width: '100%',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  card: {
    width: '100%',
    maxWidth: 360,
    backgroundColor: 'rgba(15, 15, 15, 0.55)', // Glassmorphic card styling
    borderColor: 'rgba(212, 175, 55, 0.12)',
    borderWidth: 1,
    borderRadius: 16,
    padding: 35,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.9,
    shadowRadius: 20,
    elevation: 10,
  },
  brandContainer: {
    alignItems: 'center',
    marginBottom: 35,
  },
  logo: {
    width: 100,
    height: 100,
    borderRadius: 50,
    borderWidth: 1.5,
    borderColor: '#d4af37', // Gold border matching theme
  },
  inputWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(255, 255, 255, 0.15)',
    marginBottom: 22,
    paddingVertical: 6,
  },
  icon: {
    fontSize: 16,
    color: 'rgba(255, 255, 255, 0.75)',
    marginRight: 10,
  },
  input: {
    flex: 1,
    color: '#ffffff',
    fontSize: 14,
    fontWeight: '500',
  },
  optionsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 25,
  },
  rememberBtn: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  checkbox: {
    width: 14,
    height: 14,
    borderWidth: 1.2,
    borderColor: 'rgba(255, 255, 255, 0.35)',
    borderRadius: 3,
    backgroundColor: 'rgba(0, 0, 0, 0.3)',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 8,
  },
  checkboxChecked: {
    backgroundColor: '#d4af37',
    borderColor: '#d4af37',
  },
  checkMark: {
    fontSize: 9,
    color: '#000000',
    fontWeight: '700',
  },
  rememberText: {
    color: 'rgba(255, 255, 255, 0.6)',
    fontSize: 12,
    fontWeight: '500',
  },
  forgotText: {
    color: '#d4af37',
    fontSize: 12,
    fontWeight: '600',
  },
  loginBtn: {
    backgroundColor: '#d4af37', // Minimal matte gold button
    borderRadius: 6,
    paddingVertical: 12,
    alignItems: 'center',
    marginBottom: 20,
  },
  loginBtnText: {
    color: '#000000',
    fontSize: 14,
    fontWeight: '700',
    letterSpacing: 0.5,
  },
  signUpRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
  },
  signUpText: {
    color: '#ffffff',
    fontSize: 12,
    opacity: 0.75,
  },
  signUpLink: {
    color: '#d4af37',
    fontSize: 12,
    fontWeight: '700',
  },
});
