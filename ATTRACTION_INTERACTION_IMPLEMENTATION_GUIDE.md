# Attraction Interaction API Implementation Guide

This guide provides step-by-step instructions on how to implement attraction interaction features in your frontend application using the provided APIs.

## Table of Contents
1. [Setup and Authentication](#setup-and-authentication)
2. [Basic Implementation Structure](#basic-implementation-structure)
3. [Like/Unlike Feature](#likeunlike-feature)
4. [Bookmark Feature](#bookmark-feature)
5. [Visit Recording](#visit-recording)
6. [Share Feature](#share-feature)
7. [User Collections (Liked, Bookmarked, Visited)](#user-collections)
8. [Error Handling](#error-handling)
9. [React Components Examples](#react-components-examples)
10. [State Management](#state-management)
11. [Best Practices](#best-practices)

---

## Setup and Authentication

### 1. API Base Configuration
```javascript
// config/api.js
const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:8000/api/v1';

export const apiConfig = {
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  }
};

// Get auth token from your auth system
export const getAuthToken = () => {
  return localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
};

// Add auth header to requests
export const getAuthHeaders = () => {
  const token = getAuthToken();
  return {
    ...apiConfig.headers,
    ...(token && { 'Authorization': `Bearer ${token}` })
  };
};
```

### 2. API Service Class
```javascript
// services/attractionInteractionService.js
class AttractionInteractionService {
  constructor() {
    this.baseURL = `${API_BASE_URL}/attraction-interactions`;
  }

  async makeRequest(endpoint, options = {}) {
    const url = `${this.baseURL}${endpoint}`;
    const config = {
      headers: getAuthHeaders(),
      ...options
    };

    try {
      const response = await fetch(url, config);
      const data = await response.json();
      
      if (!response.ok) {
        throw new Error(data.message || `HTTP error! status: ${response.status}`);
      }
      
      return data;
    } catch (error) {
      console.error(`API request failed: ${endpoint}`, error);
      throw error;
    }
  }

  // Store new interaction
  async storeInteraction(interactionData) {
    return this.makeRequest('', {
      method: 'POST',
      body: JSON.stringify(interactionData)
    });
  }

  // Toggle interaction (like/bookmark)
  async toggleInteraction(attractionId, interactionType, options = {}) {
    return this.makeRequest('/toggle', {
      method: 'POST',
      body: JSON.stringify({
        attraction_id: attractionId,
        interaction_type: interactionType,
        ...options
      })
    });
  }

  // Remove interaction
  async removeInteraction(attractionId, interactionType) {
    return this.makeRequest('/remove', {
      method: 'DELETE',
      body: JSON.stringify({
        attraction_id: attractionId,
        interaction_type: interactionType
      })
    });
  }

  // Get user's liked attractions
  async getLikedAttractions(page = 1, perPage = 15) {
    return this.makeRequest(`/liked?page=${page}&per_page=${perPage}`);
  }

  // Get user's bookmarked attractions
  async getBookmarkedAttractions(page = 1, perPage = 15) {
    return this.makeRequest(`/bookmarked?page=${page}&per_page=${perPage}`);
  }

  // Get user's visited attractions
  async getVisitedAttractions(page = 1, perPage = 15) {
    return this.makeRequest(`/visited?page=${page}&per_page=${perPage}`);
  }

  // Get user interactions
  async getUserInteractions(userId, interactionType = null, page = 1, perPage = 15) {
    const params = new URLSearchParams({
      page: page.toString(),
      per_page: perPage.toString(),
      ...(interactionType && { interaction_type: interactionType })
    });
    
    return this.makeRequest(`/user/${userId}?${params}`);
  }

  // Get attraction interactions
  async getAttractionInteractions(attractionId, interactionType = null, page = 1, perPage = 20) {
    const params = new URLSearchParams({
      page: page.toString(),
      per_page: perPage.toString(),
      ...(interactionType && { interaction_type: interactionType })
    });
    
    return this.makeRequest(`/attraction/${attractionId}?${params}`);
  }
}

export const attractionInteractionService = new AttractionInteractionService();
```

---

## Basic Implementation Structure

### 1. Hook for Managing Interactions
```javascript
// hooks/useAttractionInteractions.js
import { useState, useEffect, useCallback } from 'react';
import { attractionInteractionService } from '../services/attractionInteractionService';

export const useAttractionInteractions = (attractionId, initialUserInteractions = {}) => {
  const [userInteractions, setUserInteractions] = useState({
    hasLiked: initialUserInteractions.hasLiked || false,
    hasBookmarked: initialUserInteractions.hasBookmarked || false,
    hasVisited: initialUserInteractions.hasVisited || false,
    ...initialUserInteractions
  });
  
  const [stats, setStats] = useState({
    totalLikes: 0,
    totalShares: 0,
    totalDislikes: 0
  });
  
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  // Toggle like
  const toggleLike = useCallback(async (notes = '') => {
    setLoading(true);
    setError(null);
    
    try {
      const response = await attractionInteractionService.toggleInteraction(
        attractionId, 
        'like', 
        { notes, is_public: true }
      );
      
      if (response.success) {
        setUserInteractions(prev => ({
          ...prev,
          hasLiked: response.data.is_liked
        }));
        
        setStats(prev => ({
          ...prev,
          totalLikes: response.data.attraction_stats.total_likes
        }));
        
        return response.data;
      }
    } catch (err) {
      setError(err.message);
      throw err;
    } finally {
      setLoading(false);
    }
  }, [attractionId]);

  // Toggle bookmark
  const toggleBookmark = useCallback(async (options = {}) => {
    setLoading(true);
    setError(null);
    
    try {
      const response = await attractionInteractionService.toggleInteraction(
        attractionId, 
        'bookmark', 
        {
          notes: options.notes || '',
          is_public: options.isPublic || false
        }
      );
      
      if (response.success) {
        setUserInteractions(prev => ({
          ...prev,
          hasBookmarked: response.data.action === 'created'
        }));
        
        return response.data;
      }
    } catch (err) {
      setError(err.message);
      throw err;
    } finally {
      setLoading(false);
    }
  }, [attractionId]);

  // Record visit
  const recordVisit = useCallback(async (visitData) => {
    setLoading(true);
    setError(null);
    
    try {
      const response = await attractionInteractionService.storeInteraction({
        attraction_id: attractionId,
        interaction_type: 'visit',
        ...visitData
      });
      
      if (response.success) {
        setUserInteractions(prev => ({
          ...prev,
          hasVisited: true
        }));
        
        return response.data;
      }
    } catch (err) {
      setError(err.message);
      throw err;
    } finally {
      setLoading(false);
    }
  }, [attractionId]);

  // Share attraction
  const shareAttraction = useCallback(async (platform, message = '') => {
    setLoading(true);
    setError(null);
    
    try {
      const response = await attractionInteractionService.storeInteraction({
        attraction_id: attractionId,
        interaction_type: 'share',
        platform,
        message,
        is_public: true
      });
      
      if (response.success) {
        setStats(prev => ({
          ...prev,
          totalShares: response.data.attraction.total_shares
        }));
        
        return response.data;
      }
    } catch (err) {
      setError(err.message);
      throw err;
    } finally {
      setLoading(false);
    }
  }, [attractionId]);

  return {
    userInteractions,
    stats,
    loading,
    error,
    toggleLike,
    toggleBookmark,
    recordVisit,
    shareAttraction
  };
};
```

---

## Like/Unlike Feature

### 1. Like Button Component
```jsx
// components/LikeButton.jsx
import React from 'react';
import { Heart } from 'lucide-react';
import { useAttractionInteractions } from '../hooks/useAttractionInteractions';

const LikeButton = ({ attractionId, initialLiked = false, initialCount = 0, className = '' }) => {
  const { userInteractions, stats, loading, toggleLike } = useAttractionInteractions(
    attractionId, 
    { hasLiked: initialLiked }
  );

  const handleLike = async () => {
    if (loading) return;
    
    try {
      await toggleLike('Great place!');
      
      // Optional: Show success notification
      if (userInteractions.hasLiked) {
        showNotification('Added to your liked attractions!');
      } else {
        showNotification('Removed from liked attractions');
      }
    } catch (error) {
      showNotification('Failed to update like status', 'error');
    }
  };

  return (
    <button 
      onClick={handleLike}
      disabled={loading}
      className={`
        flex items-center gap-2 px-4 py-2 rounded-lg transition-all duration-200
        ${userInteractions.hasLiked 
          ? 'bg-red-100 text-red-600 border border-red-200' 
          : 'bg-gray-100 text-gray-600 border border-gray-200 hover:bg-gray-200'
        }
        ${loading ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}
        ${className}
      `}
    >
      <Heart 
        size={18} 
        className={`transition-colors ${userInteractions.hasLiked ? 'fill-current' : ''}`}
      />
      <span className="font-medium">
        {userInteractions.hasLiked ? 'Liked' : 'Like'}
      </span>
      <span className="text-sm">({stats.totalLikes || initialCount})</span>
    </button>
  );
};

export default LikeButton;
```

### 2. Usage Example
```jsx
// In your attraction detail page
<LikeButton 
  attractionId={attraction.id}
  initialLiked={attraction.user_has_liked}
  initialCount={attraction.engagement.total_likes}
  className="w-full sm:w-auto"
/>
```

---

## Bookmark Feature

### 1. Bookmark Button Component
```jsx
// components/BookmarkButton.jsx
import React, { useState } from 'react';
import { Bookmark, BookmarkCheck } from 'lucide-react';
import { useAttractionInteractions } from '../hooks/useAttractionInteractions';
import BookmarkModal from './BookmarkModal';

const BookmarkButton = ({ 
  attractionId, 
  initialBookmarked = false, 
  className = '' 
}) => {
  const [showModal, setShowModal] = useState(false);
  const { userInteractions, loading, toggleBookmark } = useAttractionInteractions(
    attractionId, 
    { hasBookmarked: initialBookmarked }
  );

  const handleBookmark = async (bookmarkData = {}) => {
    if (loading) return;
    
    try {
      if (userInteractions.hasBookmarked) {
        // Remove bookmark
        await toggleBookmark();
        showNotification('Removed from bookmarks');
      } else {
        // Add bookmark with details
        if (bookmarkData.showModal) {
          setShowModal(true);
          return;
        }
        
        await toggleBookmark(bookmarkData);
        showNotification('Added to bookmarks!');
      }
    } catch (error) {
      showNotification('Failed to update bookmark', 'error');
    }
  };

  const handleBookmarkWithDetails = async (details) => {
    try {
      await toggleBookmark({
        notes: details.notes,
        isPublic: details.isPublic
      });
      setShowModal(false);
      showNotification('Added to bookmarks with details!');
    } catch (error) {
      showNotification('Failed to save bookmark', 'error');
    }
  };

  return (
    <>
      <button 
        onClick={() => handleBookmark({ showModal: !userInteractions.hasBookmarked })}
        disabled={loading}
        className={`
          flex items-center gap-2 px-4 py-2 rounded-lg transition-all duration-200
          ${userInteractions.hasBookmarked 
            ? 'bg-blue-100 text-blue-600 border border-blue-200' 
            : 'bg-gray-100 text-gray-600 border border-gray-200 hover:bg-gray-200'
          }
          ${loading ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}
          ${className}
        `}
      >
        {userInteractions.hasBookmarked ? <BookmarkCheck size={18} /> : <Bookmark size={18} />}
        <span className="font-medium">
          {userInteractions.hasBookmarked ? 'Bookmarked' : 'Bookmark'}
        </span>
      </button>

      {showModal && (
        <BookmarkModal
          onSave={handleBookmarkWithDetails}
          onCancel={() => setShowModal(false)}
          attractionName={attraction?.name}
        />
      )}
    </>
  );
};

export default BookmarkButton;
```

### 2. Bookmark Modal Component
```jsx
// components/BookmarkModal.jsx
import React, { useState } from 'react';
import { X, Calendar, FileText, Globe, Lock } from 'lucide-react';

const BookmarkModal = ({ onSave, onCancel, attractionName }) => {
  const [formData, setFormData] = useState({
    notes: '',
    priority: 'medium',
    plannedVisitDate: '',
    isPublic: false
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    onSave(formData);
  };

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div className="bg-white rounded-xl max-w-md w-full p-6">
        <div className="flex justify-between items-center mb-4">
          <h3 className="text-lg font-semibold">Bookmark {attractionName}</h3>
          <button onClick={onCancel} className="text-gray-400 hover:text-gray-600">
            <X size={20} />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              <FileText size={16} className="inline mr-1" />
              Notes (Optional)
            </label>
            <textarea
              value={formData.notes}
              onChange={(e) => setFormData(prev => ({ ...prev, notes: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              rows="3"
              placeholder="Why do you want to visit this place?"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Priority</label>
            <select
              value={formData.priority}
              onChange={(e) => setFormData(prev => ({ ...prev, priority: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500"
            >
              <option value="low">Low Priority</option>
              <option value="medium">Medium Priority</option>
              <option value="high">High Priority</option>
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              <Calendar size={16} className="inline mr-1" />
              Planned Visit Date (Optional)
            </label>
            <input
              type="date"
              value={formData.plannedVisitDate}
              onChange={(e) => setFormData(prev => ({ ...prev, plannedVisitDate: e.target.value }))}
              min={new Date().toISOString().split('T')[0]}
              className="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div className="flex items-center gap-3">
            <input
              type="checkbox"
              id="isPublic"
              checked={formData.isPublic}
              onChange={(e) => setFormData(prev => ({ ...prev, isPublic: e.target.checked }))}
              className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <label htmlFor="isPublic" className="flex items-center text-sm text-gray-700">
              {formData.isPublic ? <Globe size={16} className="mr-1" /> : <Lock size={16} className="mr-1" />}
              Make this bookmark public
            </label>
          </div>

          <div className="flex gap-3 pt-4">
            <button
              type="button"
              onClick={onCancel}
              className="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
            >
              Save Bookmark
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default BookmarkModal;
```

---

## Visit Recording

### 1. Visit Recording Component
```jsx
// components/VisitRecorder.jsx
import React, { useState } from 'react';
import { MapPin, Calendar, Clock, Users, Star, FileText } from 'lucide-react';
import { useAttractionInteractions } from '../hooks/useAttractionInteractions';

const VisitRecorder = ({ attractionId, onSuccess, onCancel }) => {
  const [formData, setFormData] = useState({
    visitDate: new Date().toISOString().split('T')[0],
    durationMinutes: 120,
    companions: [],
    weather: '',
    rating: 0,
    notes: '',
    isPublic: true
  });

  const { recordVisit, loading } = useAttractionInteractions(attractionId);

  const companionOptions = [
    { value: 'solo', label: 'Solo' },
    { value: 'partner', label: 'Partner' },
    { value: 'friend', label: 'Friends' },
    { value: 'family', label: 'Family' },
    { value: 'group', label: 'Group' },
    { value: 'business', label: 'Business' }
  ];

  const weatherOptions = [
    'Sunny', 'Cloudy', 'Rainy', 'Stormy', 'Snowy', 'Windy', 'Foggy'
  ];

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    try {
      await recordVisit({
        visit_date: formData.visitDate,
        duration_minutes: formData.durationMinutes,
        companions: formData.companions,
        weather: formData.weather,
        rating: formData.rating,
        notes: formData.notes,
        is_public: formData.isPublic
      });
      
      showNotification('Visit recorded successfully!');
      onSuccess?.();
    } catch (error) {
      showNotification('Failed to record visit', 'error');
    }
  };

  const handleCompanionToggle = (companion) => {
    setFormData(prev => ({
      ...prev,
      companions: prev.companions.includes(companion)
        ? prev.companions.filter(c => c !== companion)
        : [...prev.companions, companion]
    }));
  };

  return (
    <div className="bg-white rounded-xl p-6 max-w-2xl mx-auto">
      <h3 className="text-xl font-semibold mb-6 flex items-center gap-2">
        <MapPin size={20} className="text-blue-600" />
        Record Your Visit
      </h3>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Visit Date */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            <Calendar size={16} className="inline mr-1" />
            Visit Date
          </label>
          <input
            type="date"
            value={formData.visitDate}
            onChange={(e) => setFormData(prev => ({ ...prev, visitDate: e.target.value }))}
            max={new Date().toISOString().split('T')[0]}
            className="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500"
            required
          />
        </div>

        {/* Duration */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            <Clock size={16} className="inline mr-1" />
            Duration (minutes)
          </label>
          <input
            type="number"
            value={formData.durationMinutes}
            onChange={(e) => setFormData(prev => ({ ...prev, durationMinutes: parseInt(e.target.value) }))}
            min="15"
            max="1440"
            step="15"
            className="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500"
          />
        </div>

        {/* Companions */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            <Users size={16} className="inline mr-1" />
            Who did you go with?
          </label>
          <div className="flex flex-wrap gap-2">
            {companionOptions.map(option => (
              <button
                key={option.value}
                type="button"
                onClick={() => handleCompanionToggle(option.value)}
                className={`
                  px-3 py-2 rounded-lg border transition-colors
                  ${formData.companions.includes(option.value)
                    ? 'bg-blue-100 border-blue-300 text-blue-700'
                    : 'bg-gray-100 border-gray-300 text-gray-700 hover:bg-gray-200'
                  }
                `}
              >
                {option.label}
              </button>
            ))}
          </div>
        </div>

        {/* Weather */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">Weather</label>
          <select
            value={formData.weather}
            onChange={(e) => setFormData(prev => ({ ...prev, weather: e.target.value }))}
            className="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select weather...</option>
            {weatherOptions.map(weather => (
              <option key={weather} value={weather.toLowerCase()}>{weather}</option>
            ))}
          </select>
        </div>

        {/* Rating */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            <Star size={16} className="inline mr-1" />
            Your Rating
          </label>
          <div className="flex gap-1">
            {[1, 2, 3, 4, 5].map(star => (
              <button
                key={star}
                type="button"
                onClick={() => setFormData(prev => ({ ...prev, rating: star }))}
                className="p-1"
              >
                <Star
                  size={24}
                  className={`
                    transition-colors
                    ${star <= formData.rating 
                      ? 'text-yellow-400 fill-current' 
                      : 'text-gray-300 hover:text-yellow-300'
                    }
                  `}
                />
              </button>
            ))}
            <span className="ml-2 text-sm text-gray-600">
              {formData.rating ? `${formData.rating}/5` : 'No rating'}
            </span>
          </div>
        </div>

        {/* Notes */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            <FileText size={16} className="inline mr-1" />
            Notes about your visit
          </label>
          <textarea
            value={formData.notes}
            onChange={(e) => setFormData(prev => ({ ...prev, notes: e.target.value }))}
            className="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500"
            rows="4"
            placeholder="How was your experience? Any tips for future visitors?"
          />
        </div>

        {/* Public/Private */}
        <div className="flex items-center gap-3">
          <input
            type="checkbox"
            id="isPublicVisit"
            checked={formData.isPublic}
            onChange={(e) => setFormData(prev => ({ ...prev, isPublic: e.target.checked }))}
            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
          <label htmlFor="isPublicVisit" className="text-sm text-gray-700">
            Make this visit record public (others can see your experience)
          </label>
        </div>

        {/* Submit Buttons */}
        <div className="flex gap-3 pt-4">
          <button
            type="button"
            onClick={onCancel}
            className="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={loading}
            className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
          >
            {loading ? 'Recording...' : 'Record Visit'}
          </button>
        </div>
      </form>
    </div>
  );
};

export default VisitRecorder;
```

---

## Share Feature

### 1. Share Button Component
```jsx
// components/ShareButton.jsx
import React, { useState } from 'react';
import { Share2, Facebook, Twitter, MessageCircle, Copy, Mail } from 'lucide-react';
import { useAttractionInteractions } from '../hooks/useAttractionInteractions';

const ShareButton = ({ attraction, className = '' }) => {
  const [showModal, setShowModal] = useState(false);
  const { shareAttraction, loading } = useAttractionInteractions(attraction.id);

  const shareOptions = [
    { 
      platform: 'facebook', 
      label: 'Facebook', 
      icon: Facebook, 
      color: 'text-blue-600',
      bgColor: 'hover:bg-blue-50' 
    },
    { 
      platform: 'twitter', 
      label: 'Twitter', 
      icon: Twitter, 
      color: 'text-sky-500',
      bgColor: 'hover:bg-sky-50' 
    },
    { 
      platform: 'whatsapp', 
      label: 'WhatsApp', 
      icon: MessageCircle, 
      color: 'text-green-600',
      bgColor: 'hover:bg-green-50' 
    },
    { 
      platform: 'email', 
      label: 'Email', 
      icon: Mail, 
      color: 'text-gray-600',
      bgColor: 'hover:bg-gray-50' 
    },
    { 
      platform: 'copy_link', 
      label: 'Copy Link', 
      icon: Copy, 
      color: 'text-purple-600',
      bgColor: 'hover:bg-purple-50' 
    }
  ];

  const handleShare = async (platform, customMessage = '') => {
    try {
      const result = await shareAttraction(platform, customMessage);
      
      if (platform === 'copy_link') {
        await navigator.clipboard.writeText(result.data.share_url);
        showNotification('Link copied to clipboard!');
      } else {
        // Open share URL in new window
        window.open(result.data.share_url, '_blank', 'width=600,height=400');
        showNotification(`Shared on ${platform}!`);
      }
      
      setShowModal(false);
    } catch (error) {
      showNotification(`Failed to share on ${platform}`, 'error');
    }
  };

  return (
    <>
      <button 
        onClick={() => setShowModal(true)}
        className={`
          flex items-center gap-2 px-4 py-2 rounded-lg transition-all duration-200
          bg-gray-100 text-gray-600 border border-gray-200 hover:bg-gray-200
          ${loading ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}
          ${className}
        `}
      >
        <Share2 size={18} />
        <span className="font-medium">Share</span>
      </button>

      {showModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl max-w-md w-full p-6">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-lg font-semibold">Share {attraction.name}</h3>
              <button 
                onClick={() => setShowModal(false)}
                className="text-gray-400 hover:text-gray-600"
              >
                <X size={20} />
              </button>
            </div>

            <div className="grid grid-cols-1 gap-2">
              {shareOptions.map(option => (
                <button
                  key={option.platform}
                  onClick={() => handleShare(option.platform, `Check out ${attraction.name}! Amazing place to visit.`)}
                  disabled={loading}
                  className={`
                    flex items-center gap-3 p-3 rounded-lg transition-colors
                    border border-gray-200 ${option.bgColor}
                    ${loading ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}
                  `}
                >
                  <option.icon size={20} className={option.color} />
                  <span className="font-medium text-gray-700">{option.label}</span>
                </button>
              ))}
            </div>

            <div className="mt-4 p-3 bg-gray-50 rounded-lg">
              <p className="text-sm text-gray-600">
                Share this amazing destination with your friends and family!
              </p>
            </div>
          </div>
        </div>
      )}
    </>
  );
};

export default ShareButton;
```

---

## User Collections

### 1. Liked Attractions Page
```jsx
// pages/LikedAttractions.jsx
import React, { useState, useEffect } from 'react';
import { Heart, MapPin, Star } from 'lucide-react';
import { attractionInteractionService } from '../services/attractionInteractionService';
import AttractionCard from '../components/AttractionCard';
import LoadingSpinner from '../components/LoadingSpinner';
import EmptyState from '../components/EmptyState';

const LikedAttractions = () => {
  const [attractions, setAttractions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [pagination, setPagination] = useState({
    currentPage: 1,
    totalPages: 1,
    total: 0
  });

  const fetchLikedAttractions = async (page = 1) => {
    try {
      setLoading(true);
      const response = await attractionInteractionService.getLikedAttractions(page, 12);
      
      if (response.success) {
        setAttractions(response.data.data);
        setPagination({
          currentPage: response.data.current_page,
          totalPages: response.data.last_page,
          total: response.data.total
        });
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLikedAttractions();
  }, []);

  const handlePageChange = (page) => {
    fetchLikedAttractions(page);
  };

  const handleUnlike = (attractionId) => {
    setAttractions(prev => prev.filter(item => item.attraction.id !== attractionId));
    setPagination(prev => ({ ...prev, total: prev.total - 1 }));
  };

  if (loading && attractions.length === 0) {
    return <LoadingSpinner />;
  }

  if (error) {
    return (
      <div className="text-center py-8">
        <p className="text-red-600">Error loading liked attractions: {error}</p>
        <button 
          onClick={() => fetchLikedAttractions()}
          className="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
        >
          Try Again
        </button>
      </div>
    );
  }

  if (attractions.length === 0) {
    return (
      <EmptyState
        icon={Heart}
        title="No Liked Attractions"
        description="Start exploring and like attractions to see them here!"
        actionLabel="Explore Attractions"
        actionLink="/attractions"
      />
    );
  }

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="flex items-center gap-3 mb-6">
        <Heart size={24} className="text-red-600 fill-current" />
        <h1 className="text-2xl font-bold text-gray-900">Liked Attractions</h1>
        <span className="bg-red-100 text-red-800 px-2 py-1 rounded-full text-sm font-medium">
          {pagination.total}
        </span>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        {attractions.map(item => (
          <AttractionCard
            key={item.id}
            attraction={item.attraction}
            interactionData={{
              likedAt: item.created_at,
              notes: item.notes
            }}
            onUnlike={() => handleUnlike(item.attraction.id)}
            showLikedDate={true}
          />
        ))}
      </div>

      {/* Pagination */}
      {pagination.totalPages > 1 && (
        <div className="flex justify-center mt-8">
          <div className="flex gap-2">
            {Array.from({ length: pagination.totalPages }, (_, i) => i + 1).map(page => (
              <button
                key={page}
                onClick={() => handlePageChange(page)}
                disabled={loading}
                className={`
                  px-3 py-2 rounded-lg transition-colors
                  ${page === pagination.currentPage
                    ? 'bg-blue-600 text-white'
                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                  }
                  ${loading ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}
                `}
              >
                {page}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

export default LikedAttractions;
```

---

## Error Handling

### 1. Error Handling Service
```javascript
// utils/errorHandler.js
export const handleApiError = (error, defaultMessage = 'Something went wrong') => {
  console.error('API Error:', error);
  
  if (error.response?.status === 401) {
    // Redirect to login
    window.location.href = '/login';
    return 'Please log in to continue';
  }
  
  if (error.response?.status === 422) {
    // Validation errors
    const validationErrors = error.response.data?.errors;
    if (validationErrors) {
      const firstError = Object.values(validationErrors)[0];
      return Array.isArray(firstError) ? firstError[0] : firstError;
    }
  }
  
  if (error.response?.status === 404) {
    return 'Resource not found';
  }
  
  if (error.response?.status >= 500) {
    return 'Server error. Please try again later.';
  }
  
  return error.message || defaultMessage;
};

// Notification service
export const showNotification = (message, type = 'success') => {
  // Implement your notification system here
  // This could be a toast library like react-hot-toast, react-toastify, etc.
  console.log(`${type.toUpperCase()}: ${message}`);
  
  // Example with react-hot-toast:
  // import toast from 'react-hot-toast';
  // if (type === 'error') {
  //   toast.error(message);
  // } else {
  //   toast.success(message);
  // }
};
```

---

## State Management

### 1. Context Provider for Interactions
```jsx
// context/InteractionContext.jsx
import React, { createContext, useContext, useReducer } from 'react';

const InteractionContext = createContext();

const interactionReducer = (state, action) => {
  switch (action.type) {
    case 'SET_USER_INTERACTIONS':
      return {
        ...state,
        userInteractions: {
          ...state.userInteractions,
          [action.attractionId]: action.interactions
        }
      };
    
    case 'UPDATE_ATTRACTION_STATS':
      return {
        ...state,
        attractionStats: {
          ...state.attractionStats,
          [action.attractionId]: {
            ...state.attractionStats[action.attractionId],
            ...action.stats
          }
        }
      };
    
    case 'ADD_TO_LIKED':
      return {
        ...state,
        likedAttractions: [...state.likedAttractions, action.attraction]
      };
    
    case 'REMOVE_FROM_LIKED':
      return {
        ...state,
        likedAttractions: state.likedAttractions.filter(
          item => item.attraction.id !== action.attractionId
        )
      };
    
    default:
      return state;
  }
};

export const InteractionProvider = ({ children }) => {
  const [state, dispatch] = useReducer(interactionReducer, {
    userInteractions: {},
    attractionStats: {},
    likedAttractions: [],
    bookmarkedAttractions: [],
    visitedAttractions: []
  });

  const value = {
    ...state,
    dispatch
  };

  return (
    <InteractionContext.Provider value={value}>
      {children}
    </InteractionContext.Provider>
  );
};

export const useInteractionContext = () => {
  const context = useContext(InteractionContext);
  if (!context) {
    throw new Error('useInteractionContext must be used within InteractionProvider');
  }
  return context;
};
```

---

## Best Practices

### 1. Performance Optimization
```javascript
// hooks/useOptimisticInteractions.js
import { useState, useCallback } from 'react';
import { attractionInteractionService } from '../services/attractionInteractionService';

export const useOptimisticInteractions = (attractionId, initialState) => {
  const [state, setState] = useState(initialState);
  const [loading, setLoading] = useState(false);

  const optimisticToggleLike = useCallback(async () => {
    // Optimistic update
    const wasLiked = state.hasLiked;
    const newLikesCount = wasLiked ? state.likesCount - 1 : state.likesCount + 1;
    
    setState(prev => ({
      ...prev,
      hasLiked: !wasLiked,
      likesCount: newLikesCount
    }));

    try {
      const response = await attractionInteractionService.toggleInteraction(
        attractionId,
        'like'
      );
      
      // Update with server response
      setState(prev => ({
        ...prev,
        hasLiked: response.data.is_liked,
        likesCount: response.data.attraction_stats.total_likes
      }));
    } catch (error) {
      // Revert optimistic update on error
      setState(prev => ({
        ...prev,
        hasLiked: wasLiked,
        likesCount: state.likesCount
      }));
      throw error;
    }
  }, [attractionId, state]);

  return {
    state,
    optimisticToggleLike,
    loading
  };
};
```

### 2. Caching and Data Management
```javascript
// utils/cache.js
class InteractionCache {
  constructor() {
    this.cache = new Map();
    this.expirationTime = 5 * 60 * 1000; // 5 minutes
  }

  set(key, data) {
    this.cache.set(key, {
      data,
      timestamp: Date.now()
    });
  }

  get(key) {
    const item = this.cache.get(key);
    if (!item) return null;

    if (Date.now() - item.timestamp > this.expirationTime) {
      this.cache.delete(key);
      return null;
    }

    return item.data;
  }

  clear() {
    this.cache.clear();
  }
}

export const interactionCache = new InteractionCache();
```

### 3. Debouncing for Rapid Interactions
```javascript
// utils/debounce.js
export const debounce = (func, wait) => {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
};

// Usage in component
const debouncedToggleLike = debounce(toggleLike, 300);
```

### 4. Analytics Tracking
```javascript
// utils/analytics.js
export const trackInteraction = (interactionType, attractionId, attractionName) => {
  // Example with Google Analytics
  if (typeof gtag !== 'undefined') {
    gtag('event', 'attraction_interaction', {
      interaction_type: interactionType,
      attraction_id: attractionId,
      attraction_name: attractionName
    });
  }

  // Example with custom analytics
  analyticsService.track('Attraction Interaction', {
    type: interactionType,
    attractionId,
    attractionName,
    timestamp: new Date().toISOString()
  });
};
```

This comprehensive implementation guide provides everything you need to integrate the attraction interaction APIs into your frontend application. The examples are production-ready and include proper error handling, loading states, and user experience considerations.