# Your Complete React Native Google OAuth Setup

Based on your Google Client IDs, here's the complete setup for your React Native app.

## Your Google Client IDs

- **Web Client ID**: `511722915060-rgd4pfrkf0cjhd3447kdid1b272dcneg.apps.googleusercontent.com` (use this in React Native)
- **Android Client ID**: `511722915060-4vdb2tujgcvjkcnetcioqba4itm9m4n5.apps.googleusercontent.com` (for Android build)
- **Your Laravel API**: Already configured ✅

## Step 1: Install React Native Google Sign-In

```bash
npm install @react-native-google-signin/google-signin
npm install @react-native-async-storage/async-storage

# For iOS
cd ios && pod install && cd ..
```

## Step 2: Configure Android

### android/app/build.gradle
```gradle
dependencies {
    implementation 'com.google.android.gms:play-services-auth:20.7.0'
    // ... other dependencies
}
```

## Step 3: Configure React Native App

### GoogleAuthService.js
```javascript
import { GoogleSignin } from '@react-native-google-signin/google-signin';

class GoogleAuthService {
  constructor() {
    this.configure();
  }

  configure() {
    GoogleSignin.configure({
      webClientId: '511722915060-rgd4pfrkf0cjhd3447kdid1b272dcneg.apps.googleusercontent.com', // Your Web Client ID
      offlineAccess: true,
      hostedDomain: '', 
      forceCodeForRefreshToken: true,
    });
  }

  async signIn() {
    try {
      await GoogleSignin.hasPlayServices();
      const userInfo = await GoogleSignin.signIn();
      
      console.log('Google Sign-In Success:', userInfo);
      console.log('ID Token:', userInfo.idToken);
      
      return {
        success: true,
        user: userInfo.user,
        idToken: userInfo.idToken, // This is what you send to your Laravel API
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

### ApiService.js
```javascript
import AsyncStorage from '@react-native-async-storage/async-storage';

const API_BASE_URL = 'http://127.0.0.1:8000/api'; // Your Laravel API

class ApiService {
  constructor() {
    this.token = null;
  }

  setToken(token) {
    this.token = token;
  }

  async authenticateWithGoogle(idToken) {
    try {
      console.log('Sending ID token to API:', idToken);
      
      const response = await fetch(`${API_BASE_URL}/auth/google/token`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          token: idToken, // Your Laravel API expects this
        }),
      });

      const data = await response.json();
      console.log('API Response:', data);

      if (data.success) {
        this.setToken(data.token);
        
        // Store token and user data
        await AsyncStorage.setItem('authToken', data.token);
        await AsyncStorage.setItem('user', JSON.stringify(data.user));
        
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
        error: 'Network error: ' + error.message,
      };
    }
  }

  async logout() {
    try {
      if (this.token) {
        await fetch(`${API_BASE_URL}/v1/auth/logout`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${this.token}`,
          },
        });
      }
      
      // Clear local storage
      await AsyncStorage.multiRemove(['authToken', 'user']);
      this.setToken(null);
      
      return { success: true };
    } catch (error) {
      console.error('Logout Error:', error);
      // Clear local storage even if API call fails
      await AsyncStorage.multiRemove(['authToken', 'user']);
      this.setToken(null);
      return { success: true };
    }
  }

  async getCurrentUser() {
    try {
      const response = await fetch(`${API_BASE_URL}/v1/auth/user`, {
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${this.token}`,
        },
      });

      const data = await response.json();
      
      if (data.success) {
        return {
          success: true,
          user: data.data.user,
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

## Step 4: Complete Login Component

### LoginScreen.js
```javascript
import React, { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  Alert,
  ActivityIndicator,
} from 'react-native';
import GoogleAuthService from '../services/GoogleAuthService';
import ApiService from '../services/ApiService';

const LoginScreen = ({ onLoginSuccess }) => {
  const [loading, setLoading] = useState(false);

  const handleGoogleSignIn = async () => {
    try {
      setLoading(true);
      
      console.log('Starting Google Sign-In...');
      
      // Step 1: Sign in with Google and get ID token
      const googleResult = await GoogleAuthService.signIn();
      
      if (!googleResult.success) {
        Alert.alert('Google Sign-In Failed', googleResult.error);
        return;
      }
      
      console.log('Google Sign-In successful, sending to API...');
      
      // Step 2: Send ID token to your Laravel API
      const apiResult = await ApiService.authenticateWithGoogle(googleResult.idToken);
      
      if (apiResult.success) {
        console.log('API Authentication successful:', apiResult.user);
        Alert.alert('Success', `Welcome, ${apiResult.user.name}!`);
        
        // Call parent component to handle successful login
        if (onLoginSuccess) {
          onLoginSuccess(apiResult.user, apiResult.token);
        }
      } else {
        Alert.alert('Authentication Failed', apiResult.error);
      }
      
    } catch (error) {
      console.error('Sign-in error:', error);
      Alert.alert('Error', 'An unexpected error occurred');
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Welcome to 5str</Text>
      <Text style={styles.subtitle}>Discover amazing places around you</Text>
      
      <TouchableOpacity
        style={[styles.googleButton, loading && styles.buttonDisabled]}
        onPress={handleGoogleSignIn}
        disabled={loading}
      >
        {loading ? (
          <ActivityIndicator color="white" size="small" />
        ) : (
          <Text style={styles.buttonText}>🔑 Sign in with Google</Text>
        )}
      </TouchableOpacity>
      
      {loading && (
        <Text style={styles.loadingText}>Authenticating...</Text>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
    backgroundColor: '#f8f9fa',
  },
  title: {
    fontSize: 28,
    fontWeight: 'bold',
    marginBottom: 10,
    color: '#2c3e50',
    textAlign: 'center',
  },
  subtitle: {
    fontSize: 16,
    color: '#7f8c8d',
    marginBottom: 50,
    textAlign: 'center',
    lineHeight: 22,
  },
  googleButton: {
    backgroundColor: '#4285F4',
    paddingHorizontal: 40,
    paddingVertical: 15,
    borderRadius: 25,
    minWidth: 250,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.1,
    shadowRadius: 3.84,
    elevation: 5,
  },
  buttonDisabled: {
    backgroundColor: '#a0a0a0',
  },
  buttonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: '600',
  },
  loadingText: {
    marginTop: 20,
    color: '#7f8c8d',
    fontSize: 14,
  },
});

export default LoginScreen;
```

## Step 5: Test Your Implementation

### Testing Checklist

1. **Test the Google Sign-In Flow:**
```javascript
// Add this to your component to test
const testGoogleAuth = async () => {
  const result = await GoogleAuthService.signIn();
  console.log('Google Result:', result);
  
  if (result.success) {
    const apiResult = await ApiService.authenticateWithGoogle(result.idToken);
    console.log('API Result:', apiResult);
  }
};
```

2. **Check Your Laravel API Response:**
```bash
# Test with curl (replace with actual token)
curl -X POST "http://127.0.0.1:8000/api/auth/google/token" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"token":"YOUR_GOOGLE_ID_TOKEN_HERE"}'
```

3. **Expected Response from Your API:**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@gmail.com",
    "phone": null,
    "city": null,
    "avatar": "https://lh3.googleusercontent.com/...",
    "profile_image": null,
    "current_latitude": null,
    "current_longitude": null,
    "total_points": 0,
    "trust_level": 1,
    "email_verified_at": "2025-11-15T...",
    "is_active": true,
    "google_id": "1234567890"
  },
  "token": "1|abcdef123456...",
  "token_type": "Bearer"
}
```

## Troubleshooting

### Common Issues:

1. **"Sign in failed" error**
   - Make sure you're using the correct Web Client ID in React Native
   - Verify your Android Client ID is added to Google Console

2. **API returns "Invalid Google token"**
   - Check if your Laravel `.env` has the correct `GOOGLE_CLIENT_ID`
   - Make sure the Web Client ID in React Native matches the one in Laravel

3. **Network errors**
   - Update `API_BASE_URL` to your actual Laravel API URL
   - Make sure your Laravel API is running on the specified port

Your setup is now complete! Your React Native app will use the Web Client ID to get tokens from Google, then send them to your Laravel API for verification and user creation. 🎉