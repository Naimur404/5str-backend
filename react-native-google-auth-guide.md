# React Native Google OAuth Integration Guide

This guide shows how to integrate **Google OAuth ONLY** with your Laravel API in React Native. No email/password login - just Google authentication.

## Prerequisites

- React Native development environment set up
- Your Laravel API running with Google OAuth configured
- Google Cloud Console project with OAuth credentials

## Step 1: Install Required Packages

```bash
npm install @react-native-google-signin/google-signin
# For iOS
cd ios && pod install && cd ..
```

## Step 2: Configure Google Sign-In

### Android Configuration

1. **Add to `android/app/build.gradle`:**
```gradle
dependencies {
    implementation 'com.google.android.gms:play-services-auth:20.7.0'
    // ... other dependencies
}
```

2. **Get your Web Client ID from Google Console:**
   - Go to Google Cloud Console
   - Navigate to APIs & Credentials
   - Find your OAuth 2.0 Client ID (Web application type)
   - Copy the Client ID

### iOS Configuration

1. **Add to `ios/YourApp/Info.plist`:**
```xml
<key>CFBundleURLTypes</key>
<array>
    <dict>
        <key>CFBundleURLName</key>
        <string>googleauth</string>
        <key>CFBundleURLSchemes</key>
        <array>
            <string>YOUR_REVERSED_CLIENT_ID</string>
        </array>
    </dict>
</array>
```

2. **Add GoogleService-Info.plist to your iOS project**

## Step 3: Configure Google Sign-In in React Native

### Create GoogleAuth Service

Create `src/services/GoogleAuthService.js`:

```javascript
import { GoogleSignin } from '@react-native-google-signin/google-signin';

class GoogleAuthService {
  constructor() {
    this.configure();
  }

  configure() {
    GoogleSignin.configure({
      webClientId: 'YOUR_WEB_CLIENT_ID', // From Google Console
      offlineAccess: true, // For server-side validation
      hostedDomain: '', // Optional: restrict to specific domain
      forceCodeForRefreshToken: true,
    });
  }

  async signIn() {
    try {
      // Check if device supports Google Play Services
      await GoogleSignin.hasPlayServices();
      
      // Sign in and get user info
      const userInfo = await GoogleSignin.signIn();
      
      return {
        success: true,
        user: userInfo.user,
        idToken: userInfo.idToken,
        serverAuthCode: userInfo.serverAuthCode,
      };
    } catch (error) {
      console.error('Google Sign-In Error:', error);
      return {
        success: false,
        error: error.message,
      };
    }
  }

  async signOut() {
    try {
      await GoogleSignin.signOut();
      return { success: true };
    } catch (error) {
      console.error('Google Sign-Out Error:', error);
      return { success: false, error: error.message };
    }
  }

  async getCurrentUser() {
    try {
      const userInfo = await GoogleSignin.getCurrentUser();
      return userInfo;
    } catch (error) {
      return null;
    }
  }

  async isSignedIn() {
    return await GoogleSignin.isSignedIn();
  }
}

export default new GoogleAuthService();
```

## Step 4: Create API Service

Create `src/services/ApiService.js`:

```javascript
const API_BASE_URL = 'http://your-laravel-api.com/api'; // Replace with your API URL

class ApiService {
  constructor() {
    this.token = null;
  }

  setToken(token) {
    this.token = token;
  }

  // Google OAuth authentication (Primary method)
  async authenticateWithGoogle(idToken) {
    try {
      const response = await fetch(`${API_BASE_URL}/auth/google/token`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          token: idToken,
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.setToken(data.token);
        return {
          success: true,
          user: data.user,
          token: data.token,
        };
      } else {
        return {
          success: false,
          error: data.error || 'Authentication failed',
        };
      }
    } catch (error) {
      console.error('API Authentication Error:', error);
      return {
        success: false,
        error: 'Network error occurred',
      };
    }
  }

  // Get current user profile
  async getCurrentUser() {
    try {
      const response = await this.makeAuthenticatedRequest('/v1/auth/user');
      
      if (response.success) {
        return {
          success: true,
          user: response.data.user,
        };
      } else {
        return {
          success: false,
          error: 'Failed to get user profile',
        };
      }
    } catch (error) {
      console.error('Get User Error:', error);
      return {
        success: false,
        error: 'Network error occurred',
      };
    }
  }

  // Update user profile
  async updateProfile(profileData) {
    try {
      const response = await this.makeAuthenticatedRequest('/v1/auth/profile', {
        method: 'PUT',
        body: JSON.stringify(profileData),
      });

      if (response.success) {
        return {
          success: true,
          user: response.data.user,
          message: response.message,
        };
      } else {
        return {
          success: false,
          error: response.message || 'Profile update failed',
          errors: response.errors || {},
        };
      }
    } catch (error) {
      console.error('Update Profile Error:', error);
      return {
        success: false,
        error: 'Network error occurred',
      };
    }
  }

  // Logout
  async logout() {
    try {
      await this.makeAuthenticatedRequest('/v1/auth/logout', {
        method: 'POST',
      });

      this.setToken(null);
      return { success: true };
    } catch (error) {
      console.error('Logout Error:', error);
      // Even if the API call fails, clear the local token
      this.setToken(null);
      return { success: true };
    }
  }

  async makeAuthenticatedRequest(endpoint, options = {}) {
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...options.headers,
    };

    if (this.token) {
      headers.Authorization = `Bearer ${this.token}`;
    }

    const response = await fetch(`${API_BASE_URL}${endpoint}`, {
      ...options,
      headers,
    });

    return response.json();
  }
}

export default new ApiService();
```

## Step 5: Create Auth Context

Create `src/context/AuthContext.js`:

```javascript
import React, { createContext, useContext, useState, useEffect } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import GoogleAuthService from '../services/GoogleAuthService';
import ApiService from '../services/ApiService';

const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [token, setToken] = useState(null);

  useEffect(() => {
    checkAuthStatus();
  }, []);

  const checkAuthStatus = async () => {
    try {
      const savedToken = await AsyncStorage.getItem('authToken');
      const savedUser = await AsyncStorage.getItem('user');

      if (savedToken && savedUser) {
        setToken(savedToken);
        setUser(JSON.parse(savedUser));
        ApiService.setToken(savedToken);
      }
    } catch (error) {
      console.error('Auth check error:', error);
    } finally {
      setLoading(false);
    }
  };

  const signInWithGoogle = async () => {
    try {
      setLoading(true);
      
      // Sign in with Google
      const googleResult = await GoogleAuthService.signIn();
      
      if (!googleResult.success) {
        throw new Error(googleResult.error);
      }

      // Authenticate with your API
      const apiResult = await ApiService.authenticateWithGoogle(googleResult.idToken);
      
      if (!apiResult.success) {
        throw new Error(apiResult.error);
      }

      // Save to storage
      await AsyncStorage.setItem('authToken', apiResult.token);
      await AsyncStorage.setItem('user', JSON.stringify(apiResult.user));

      setToken(apiResult.token);
      setUser(apiResult.user);

      return { success: true };
    } catch (error) {
      console.error('Sign in error:', error);
      return { success: false, error: error.message };
    } finally {
      setLoading(false);
    }
  };

  const signOut = async () => {
    try {
      setLoading(true);
      
      // Sign out from Google
      await GoogleAuthService.signOut();
      
      // Clear storage
      await AsyncStorage.multiRemove(['authToken', 'user']);
      
      setToken(null);
      setUser(null);
      ApiService.setToken(null);
      
      return { success: true };
    } catch (error) {
      console.error('Sign out error:', error);
      return { success: false, error: error.message };
    } finally {
      setLoading(false);
    }
  };

  const value = {
    user,
    token,
    loading,
    signInWithGoogle,
    signOut,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
```

## Step 6: Create Login Screen Component

Create `src/screens/LoginScreen.js`:

```javascript
import React from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { useAuth } from '../context/AuthContext';

const LoginScreen = () => {
  const { signInWithGoogle, loading } = useAuth();

  const handleGoogleSignIn = async () => {
    const result = await signInWithGoogle();
    
    if (!result.success) {
      Alert.alert('Sign In Failed', result.error);
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Welcome to Your App</Text>
      <Text style={styles.subtitle}>Sign in with your Google account to continue</Text>
      
      <TouchableOpacity
        style={styles.googleButton}
        onPress={handleGoogleSignIn}
        disabled={loading}
      >
        {loading ? (
          <ActivityIndicator color="white" />
        ) : (
          <>
            <Text style={styles.buttonText}>🔑 Sign in with Google</Text>
          </>
        )}
      </TouchableOpacity>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
    backgroundColor: '#f5f5f5',
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    marginBottom: 10,
    color: '#333',
    textAlign: 'center',
  },
  subtitle: {
    fontSize: 16,
    color: '#666',
    marginBottom: 40,
    textAlign: 'center',
    lineHeight: 22,
  },
  googleButton: {
    backgroundColor: '#4285F4',
    paddingHorizontal: 30,
    paddingVertical: 12,
    borderRadius: 25,
    minWidth: 200,
    alignItems: 'center',
  },
  buttonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: '600',
  },
});

export default LoginScreen;
```

## Step 7: Create Home Screen Component

Create `src/screens/HomeScreen.js`:

```javascript
import React from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  Alert,
  Image,
} from 'react-native';
import { useAuth } from '../context/AuthContext';

const HomeScreen = () => {
  const { user, signOut } = useAuth();

  const handleSignOut = async () => {
    Alert.alert(
      'Sign Out',
      'Are you sure you want to sign out?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Sign Out',
          style: 'destructive',
          onPress: async () => {
            const result = await signOut();
            if (!result.success) {
              Alert.alert('Error', result.error);
            }
          },
        },
      ]
    );
  };

  return (
    <View style={styles.container}>
      <Text style={styles.welcome}>Welcome!</Text>
      
      {user?.avatar && (
        <Image source={{ uri: user.avatar }} style={styles.avatar} />
      )}
      
      <Text style={styles.name}>{user?.name}</Text>
      <Text style={styles.email}>{user?.email}</Text>
      
      <TouchableOpacity style={styles.signOutButton} onPress={handleSignOut}>
        <Text style={styles.buttonText}>Sign Out</Text>
      </TouchableOpacity>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
    backgroundColor: '#f5f5f5',
  },
  welcome: {
    fontSize: 24,
    fontWeight: 'bold',
    marginBottom: 20,
    color: '#333',
  },
  avatar: {
    width: 100,
    height: 100,
    borderRadius: 50,
    marginBottom: 20,
  },
  name: {
    fontSize: 20,
    fontWeight: '600',
    marginBottom: 10,
    color: '#333',
  },
  email: {
    fontSize: 16,
    color: '#666',
    marginBottom: 40,
  },
  signOutButton: {
    backgroundColor: '#FF3B30',
    paddingHorizontal: 30,
    paddingVertical: 12,
    borderRadius: 25,
  },
  buttonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: '600',
  },
});

export default HomeScreen;
```

## Step 8: Update App.js

```javascript
import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createStackNavigator } from '@react-navigation/stack';
import { AuthProvider, useAuth } from './src/context/AuthContext';
import LoginScreen from './src/screens/LoginScreen';
import HomeScreen from './src/screens/HomeScreen';
import { ActivityIndicator, View } from 'react-native';

const Stack = createStackNavigator();

const AppNavigator = () => {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
        <ActivityIndicator size="large" color="#4285F4" />
      </View>
    );
  }

  return (
    <NavigationContainer>
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        {user ? (
          <Stack.Screen name="Home" component={HomeScreen} />
        ) : (
          <Stack.Screen name="Login" component={LoginScreen} />
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
};

const App = () => {
  return (
    <AuthProvider>
      <AppNavigator />
    </AuthProvider>
  );
};

export default App;
```

## Step 9: Install Additional Dependencies

```bash
npm install @react-native-async-storage/async-storage
npm install @react-navigation/native @react-navigation/stack
npm install react-native-screens react-native-safe-area-context

# For iOS
cd ios && pod install && cd ..
```

## Step 10: Configuration Checklist

### Google Console Setup:
1. ✅ Enable Google+ API (or Google Identity API)
2. ✅ Configure OAuth consent screen
3. ✅ Add authorized domains
4. ✅ Create OAuth 2.0 Client IDs for:
   - Web application (for your Laravel API)
   - Android application (if deploying to Android)
   - iOS application (if deploying to iOS)

### Laravel API Setup:
1. ✅ Add Google credentials to `.env`:
```env
GOOGLE_CLIENT_ID=your_web_client_id
GOOGLE_CLIENT_SECRET=your_web_client_secret
GOOGLE_REDIRECT_URL=http://your-app.com/auth/google/callback
```

### React Native Setup:
1. ✅ Replace `YOUR_WEB_CLIENT_ID` with your actual web client ID
2. ✅ Replace `YOUR_REVERSED_CLIENT_ID` with your iOS reversed client ID
3. ✅ Update `API_BASE_URL` in ApiService.js
4. ✅ Add required permissions to AndroidManifest.xml

## Authentication Flow Summary

1. **User opens app** → Shows Google Sign-In button
2. **User taps button** → Google OAuth popup appears
3. **User signs in** → Google returns ID token
4. **App sends token** → Your Laravel API (`/api/auth/google/token`)
5. **API validates** → Returns Sanctum token + user data
6. **App stores token** → User is now authenticated
7. **Subsequent requests** → Use Sanctum token for API calls

## Available API Endpoints After Authentication

Once authenticated with Google, your app can use these Laravel API endpoints:

```javascript
// Get user profile
const profile = await ApiService.getCurrentUser();

// Update user profile  
const result = await ApiService.updateProfile({
  name: 'New Name',
  city: 'New York',
  current_latitude: 40.7128,
  current_longitude: -74.0060
});

// Logout
await ApiService.logout();

// Make any authenticated request
const data = await ApiService.makeAuthenticatedRequest('/v1/businesses/nearby');
```

## Troubleshooting

### Common Issues:

1. **"Sign in failed" error**
   - Check if your web client ID is correct
   - Ensure your SHA1 fingerprint is added to Google Console (Android)

2. **Network errors**
   - Verify your API URL is accessible
   - Check if your Laravel API is running

3. **iOS build issues**
   - Make sure GoogleService-Info.plist is properly added
   - Run `cd ios && pod install`

4. **Android build issues**
   - Check if Google Play Services is available on the device/emulator
   - Verify SHA1 fingerprint in Google Console

## Testing

1. **Development:**
   - Use Android emulator with Google Play Services
   - Use iOS simulator or physical device

2. **Production:**
   - Test with release builds
   - Verify production API endpoints
   - Test on various devices

## Security Best Practices

1. 🔒 Store tokens securely using AsyncStorage or Keychain
2. 🔒 Implement token refresh mechanism
3. 🔒 Validate tokens on the server side
4. 🔒 Use HTTPS in production
5. 🔒 Implement proper error handling
6. 🔒 Add token expiration handling

That's it! Your React Native app should now be able to authenticate users with Google OAuth through your Laravel API. 🎉