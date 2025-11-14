# React Native Social Login Implementation Guide

## 1. Install Required Dependencies

```bash
# Google Sign-In for React Native
npm install @react-native-google-signin/google-signin

# For iOS
cd ios && pod install

# Facebook SDK (optional)
npm install react-native-fbsdk-next

# AsyncStorage for token management
npm install @react-native-async-storage/async-storage

# HTTP client
npm install axios
```

## 2. Configure Google Sign-In (Without Firebase)

#### Step-by-step Google Cloud Console Setup:
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable "Google Sign-In API" in the API Library
4. Go to "Credentials" → "Create Credentials" → "OAuth 2.0 Client IDs"
5. Create **THREE** OAuth clients:
   - **Web application**: For your Laravel backend
   - **Android**: For your Android app
   - **iOS**: For your iOS app

#### Get SHA-1 Fingerprint for Android:
```bash
# Debug keystore (development)
keytool -list -v -keystore ~/.android/debug.keystore -alias androiddebugkey -storepass android -keypass android

# Release keystore (production) 
keytool -list -v -keystore path/to/your/release.keystore -alias your_alias
```

#### Android Configuration
Add to `android/app/build.gradle`:
```gradle
android {
    defaultConfig {
        // Add this
        manifestPlaceholders = [
            googleWebClientId: "YOUR_WEB_CLIENT_ID_HERE"
        ]
    }
}
```

#### iOS Configuration
1. Use the **Web Client ID** (not iOS client ID) in your React Native code
2. Add URL scheme to `ios/YourApp/Info.plist`:
```xml
<key>CFBundleURLTypes</key>
<array>
    <dict>
        <key>CFBundleURLName</key>
        <string>google</string>
        <key>CFBundleURLSchemes</key>
        <array>
            <string>com.googleusercontent.apps.YOUR_IOS_CLIENT_ID</string>
        </array>
    </dict>
</array>
```

**Important**: Use the **Web Client ID** in your React Native configuration, NOT the Android/iOS client IDs!

## 3. React Native Google Login Implementation

```javascript
// services/AuthService.js
import { GoogleSignin } from '@react-native-google-signin/google-signin';
import AsyncStorage from '@react-native-async-storage/async-storage';
import axios from 'axios';

class AuthService {
  constructor() {
    this.baseURL = 'https://your-api-domain.com/api/v1';
    this.initializeGoogleSignin();
  }

  initializeGoogleSignin() {
    GoogleSignin.configure({
      webClientId: 'YOUR_GOOGLE_WEB_CLIENT_ID', // From Google Cloud Console
      offlineAccess: true,
      hostedDomain: '', // Optional - restrict to specific domain
      forceCodeForRefreshToken: true, // Android only
    });
  }

  // Google Sign In
  async signInWithGoogle() {
    try {
      // Check if device supports Google Play Services
      await GoogleSignin.hasPlayServices();
      
      // Get user info and id token
      const userInfo = await GoogleSignin.signIn();
      const { idToken } = userInfo;

      // Send to your Laravel API
      const response = await axios.post(`${this.baseURL}/auth/google/token`, {
        token: idToken
      });

      if (response.data.success) {
        // Store token in secure storage
        await this.storeAuthToken(response.data.token);
        await this.storeUserData(response.data.user);
        
        return {
          success: true,
          user: response.data.user,
          token: response.data.token
        };
      }
    } catch (error) {
      console.error('Google Sign-In Error:', error);
      throw error;
    }
  }

  // Store auth token
  async storeAuthToken(token) {
    await AsyncStorage.setItem('auth_token', token);
  }

  // Store user data
  async storeUserData(user) {
    await AsyncStorage.setItem('user_data', JSON.stringify(user));
  }

  // Get stored token
  async getAuthToken() {
    return await AsyncStorage.getItem('auth_token');
  }

  // Get stored user data
  async getUserData() {
    const userData = await AsyncStorage.getItem('user_data');
    return userData ? JSON.parse(userData) : null;
  }

  // Logout
  async logout() {
    try {
      // Sign out from Google
      await GoogleSignin.signOut();
      
      // Send logout request to API
      const token = await this.getAuthToken();
      if (token) {
        await axios.post(`${this.baseURL}/auth/logout`, {}, {
          headers: { Authorization: `Bearer ${token}` }
        });
      }
      
      // Clear local storage
      await AsyncStorage.multiRemove(['auth_token', 'user_data']);
    } catch (error) {
      console.error('Logout Error:', error);
    }
  }

  // Check if user is authenticated
  async isAuthenticated() {
    const token = await this.getAuthToken();
    return !!token;
  }
}

export default new AuthService();
```

## 4. React Native Login Component

```javascript
// components/LoginScreen.js
import React, { useState } from 'react';
import { View, Text, TouchableOpacity, Alert, ActivityIndicator } from 'react-native';
import AuthService from '../services/AuthService';

const LoginScreen = ({ navigation }) => {
  const [loading, setLoading] = useState(false);

  const handleGoogleLogin = async () => {
    setLoading(true);
    
    try {
      const result = await AuthService.signInWithGoogle();
      
      if (result.success) {
        Alert.alert('Success', 'Logged in successfully!');
        navigation.navigate('Home');
      }
    } catch (error) {
      Alert.alert('Error', 'Failed to login with Google');
      console.error(error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Welcome</Text>
      
      <TouchableOpacity 
        style={styles.googleButton}
        onPress={handleGoogleLogin}
        disabled={loading}
      >
        {loading ? (
          <ActivityIndicator color="white" />
        ) : (
          <Text style={styles.buttonText}>Continue with Google</Text>
        )}
      </TouchableOpacity>
    </View>
  );
};

const styles = {
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  title: {
    fontSize: 24,
    marginBottom: 30,
    fontWeight: 'bold',
  },
  googleButton: {
    backgroundColor: '#4285F4',
    padding: 15,
    borderRadius: 8,
    width: '100%',
    alignItems: 'center',
  },
  buttonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: 'bold',
  },
};

export default LoginScreen;
```

## 5. API Client with Authentication

```javascript
// services/ApiClient.js
import axios from 'axios';
import AuthService from './AuthService';

const ApiClient = axios.create({
  baseURL: 'https://your-api-domain.com/api/v1',
  timeout: 10000,
});

// Request interceptor to add auth token
ApiClient.interceptors.request.use(
  async (config) => {
    const token = await AuthService.getAuthToken();
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor to handle 401 errors
ApiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      // Token expired or invalid
      await AuthService.logout();
      // Navigate to login screen
      // You might want to use a navigation service here
    }
    return Promise.reject(error);
  }
);

export default ApiClient;
```

## 6. Protected Route Component

```javascript
// components/ProtectedRoute.js
import React, { useEffect, useState } from 'react';
import { View, ActivityIndicator } from 'react-native';
import AuthService from '../services/AuthService';

const ProtectedRoute = ({ children, navigation }) => {
  const [isAuthenticated, setIsAuthenticated] = useState(null);

  useEffect(() => {
    checkAuthentication();
  }, []);

  const checkAuthentication = async () => {
    const authenticated = await AuthService.isAuthenticated();
    setIsAuthenticated(authenticated);
    
    if (!authenticated) {
      navigation.navigate('Login');
    }
  };

  if (isAuthenticated === null) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
        <ActivityIndicator size="large" />
      </View>
    );
  }

  return isAuthenticated ? children : null;
};

export default ProtectedRoute;
```

## 7. Usage in App Component

```javascript
// App.js
import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createStackNavigator } from '@react-navigation/stack';
import LoginScreen from './components/LoginScreen';
import HomeScreen from './components/HomeScreen';
import ProtectedRoute from './components/ProtectedRoute';

const Stack = createStackNavigator();

const App = () => {
  return (
    <NavigationContainer>
      <Stack.Navigator initialRouteName="Login">
        <Stack.Screen name="Login" component={LoginScreen} />
        <Stack.Screen name="Home">
          {(props) => (
            <ProtectedRoute {...props}>
              <HomeScreen {...props} />
            </ProtectedRoute>
          )}
        </Stack.Screen>
      </Stack.Navigator>
    </NavigationContainer>
  );
};

export default App;
```

## 8. Making API Calls

```javascript
// Example usage in any component
import ApiClient from '../services/ApiClient';

const getUserFavorites = async () => {
  try {
    const response = await ApiClient.get('/user/favorites');
    return response.data;
  } catch (error) {
    console.error('Failed to fetch favorites:', error);
    throw error;
  }
};

const addFavorite = async (businessId) => {
  try {
    const response = await ApiClient.post('/user/favorites', {
      business_id: businessId,
      type: 'business'
    });
    return response.data;
  } catch (error) {
    console.error('Failed to add favorite:', error);
    throw error;
  }
};
```

## 9. Environment Configuration

```javascript
// config/env.js
const config = {
  API_BASE_URL: __DEV__ 
    ? 'http://localhost:8000/api/v1' 
    : 'https://your-production-domain.com/api/v1',
  GOOGLE_WEB_CLIENT_ID: 'your-google-web-client-id',
};

export default config;
```

## Key Benefits of This Implementation

1. **Stateless**: No server-side sessions, perfect for mobile apps
2. **Secure**: Uses Sanctum tokens with proper expiration
3. **Scalable**: Can handle multiple mobile app instances
4. **Flexible**: Easy to add more social providers (Facebook, Apple, etc.)
5. **Persistent**: Tokens stored locally for offline capability

## Why This Approach Works Better for Your Laravel API

### ✅ **Benefits of No-Firebase Implementation:**
- **Simpler setup**: Only Google Cloud Console needed
- **Smaller app size**: No Firebase SDK overhead  
- **Direct integration**: Works perfectly with your Laravel Sanctum API
- **Full control**: You manage all authentication logic
- **No vendor lock-in**: Pure Google OAuth, not Firebase-specific

### 🔄 **The Authentication Flow:**
1. User taps "Sign in with Google" in React Native
2. Google SDK handles OAuth and returns an ID token
3. Your app sends this token to your Laravel API (`/auth/google/token`)
4. Laravel verifies the token with Google and returns a Sanctum Bearer token
5. React Native stores this token for all subsequent API calls

### 🎯 **Perfect Match with Your API:**
Your existing Laravel endpoint `/api/v1/auth/google/token` already handles everything needed:
- ✅ Verifies Google ID tokens
- ✅ Creates/links user accounts  
- ✅ Returns Sanctum Bearer tokens
- ✅ Works with your existing protected routes

## Additional Social Providers

To add Facebook login, create similar functions:

```javascript
// Facebook Login (optional)
import { LoginManager, AccessToken } from 'react-native-fbsdk-next';

async signInWithFacebook() {
  try {
    const result = await LoginManager.logInWithPermissions(['public_profile', 'email']);
    
    if (!result.isCancelled) {
      const data = await AccessToken.getCurrentAccessToken();
      
      // Send to your Laravel API (you'll need a Facebook endpoint)
      const response = await axios.post(`${this.baseURL}/auth/facebook/token`, {
        token: data.accessToken
      });
      
      // Handle response similar to Google
    }
  } catch (error) {
    console.error('Facebook Login Error:', error);
  }
}
```

This implementation provides a complete, production-ready social authentication system for your React Native app that works seamlessly with your Laravel API!