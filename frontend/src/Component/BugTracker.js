import React, { useState } from 'react';
import { ChevronDown, ChevronRight, Bug, Image, Terminal, Globe, Zap, Copy, Calendar, User } from 'lucide-react';

const BugTracker = () => {
  const [expandedSections, setExpandedSections] = useState({
    console: false,
    ajax: false,
    navigation: false,
    images: false
  });

  // Sample bug data
  const bugData = {
    id: "BUG-001",
    title: "User Authentication Failed on Login Page",
    description: "Users are experiencing authentication failures when attempting to log in with valid credentials. The error occurs intermittently and affects approximately 15% of login attempts.",
    severity: "High",
    status: "Open",
    reportedBy: "john.doe@company.com",
    reportedAt: "2024-01-15 14:30:22",
    images: [
      { id: 1, name: "login-error.png", url: "https://via.placeholder.com/400x300/ff6b6b/ffffff?text=Login+Error+Screenshot" },
      { id: 2, name: "network-tab.png", url: "https://via.placeholder.com/400x300/4ecdc4/ffffff?text=Network+Tab+Screenshot" }
    ],
    stackTrace: {
      console: [
        { 
          timestamp: "14:30:22.145", 
          level: "error", 
          message: "Uncaught TypeError: Cannot read property 'token' of undefined",
          file: "auth.js:142"
        },
        { 
          timestamp: "14:30:22.147", 
          level: "error", 
          message: "Failed to authenticate user",
          file: "login.js:87"
        }
      ],
      ajaxCalls: [
        {
          method: "POST",
          url: "/api/auth/login",
          status: 200,
          responseTime: "1.2s",
          timestamp: "14:30:22.100",
          requestHeaders: {
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest"
          },
          requestBody: { email: "user@example.com", password: "***" },
          responseBody: { success: false, error: "Invalid token format" }
        }
      ],
      navigation: [
        { timestamp: "14:30:15.000", action: "navigate", url: "/login", referrer: "/dashboard" },
        { timestamp: "14:30:22.100", action: "form_submit", url: "/login", data: "login_form" }
      ]
    }
  };

  // Component methods (same as before)
  const toggleSection = (section) => {
    setExpandedSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }));
  };

  const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
  };

  const getLogLevelColor = (level) => {
    switch (level) {
      case 'error': return 'text-red-600 bg-red-50';
      case 'warn': return 'text-yellow-600 bg-yellow-50';
      case 'info': return 'text-blue-600 bg-blue-50';
      default: return 'text-gray-600 bg-gray-50';
    }
  };

  const getStatusColor = (status) => {
    switch (status.toLowerCase()) {
      case 'open': return 'bg-red-100 text-red-800';
      case 'in progress': return 'bg-yellow-100 text-yellow-800';
      case 'resolved': return 'bg-green-100 text-green-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getSeverityColor = (severity) => {
    switch (severity.toLowerCase()) {
      case 'critical': return 'bg-red-500 text-white';
      case 'high': return 'bg-orange-500 text-white';
      case 'medium': return 'bg-yellow-500 text-white';
      case 'low': return 'bg-green-500 text-white';
      default: return 'bg-gray-500 text-white';
    }
  };

  return (
    <div className="max-w-6xl mx-auto p-6 bg-white min-h-screen">
      {/* Header */}
      <div className="mb-6 border-b border-gray-200 pb-4">
        <div className="flex items-center justify-between mb-2">
          <div className="flex items-center space-x-3">
            <Bug className="h-6 w-6 text-red-500" />
            <h1 className="text-2xl font-bold text-gray-900">{bugData.id}</h1>
            <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(bugData.status)}`}>
              {bugData.status}
            </span>
            <span className={`px-2 py-1 rounded-full text-xs font-medium ${getSeverityColor(bugData.severity)}`}>
              {bugData.severity}
            </span>
          </div>
        </div>
        <h2 className="text-xl text-gray-700 mb-3">{bugData.title}</h2>
        <div className="flex items-center space-x-4 text-sm text-gray-500">
          <div className="flex items-center space-x-1">
            <User className="h-4 w-4" />
            <span>{bugData.reportedBy}</span>
          </div>
          <div className="flex items-center space-x-1">
            <Calendar className="h-4 w-4" />
            <span>{bugData.reportedAt}</span>
          </div>
        </div>
      </div>

      {/* Description */}
      <div className="mb-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-3">Description</h3>
        <div className="bg-gray-50 rounded-lg p-4">
          <p className="text-gray-700 leading-relaxed">{bugData.description}</p>
        </div>
      </div>

      {/* Images Section */}
      <div className="mb-6">
        <button
          onClick={() => toggleSection('images')}
          className="flex items-center space-x-2 text-lg font-semibold text-gray-900 mb-3 hover:text-blue-600"
        >
          {expandedSections.images ? <ChevronDown className="h-5 w-5" /> : <ChevronRight className="h-5 w-5" />}
          <Image className="h-5 w-5" />
          <span>Screenshots & Images ({bugData.images.length})</span>
        </button>
        {expandedSections.images && (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {bugData.images.map((image) => (
              <div key={image.id} className="border border-gray-200 rounded-lg overflow-hidden">
                <img
                  src={image.url}
                  alt={image.name}
                  className="w-full h-48 object-cover"
                />
                <div className="p-3 bg-gray-50">
                  <p className="text-sm font-medium text-gray-900">{image.name}</p>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Console Logs */}
      <div className="mb-6">
        <button
          onClick={() => toggleSection('console')}
          className="flex items-center space-x-2 text-lg font-semibold text-gray-900 mb-3 hover:text-blue-600"
        >
          {expandedSections.console ? <ChevronDown className="h-5 w-5" /> : <ChevronRight className="h-5 w-5" />}
          <Terminal className="h-5 w-5" />
          <span>Console Logs ({bugData.stackTrace.console.length})</span>
        </button>
        {expandedSections.console && (
          <div className="bg-gray-900 rounded-lg p-4">
            <div className="space-y-2">
              {bugData.stackTrace.console.map((log, index) => (
                <div key={index} className="flex items-start space-x-3 font-mono text-sm">
                  <span className="text-gray-400 min-w-0 flex-shrink-0">{log.timestamp}</span>
                  <span className={`px-2 py-1 rounded text-xs font-medium min-w-0 flex-shrink-0 ${getLogLevelColor(log.level)}`}>
                    {log.level.toUpperCase()}
                  </span>
                  <span className="text-white flex-1 min-w-0">{log.message}</span>
                  <span className="text-gray-400 min-w-0 flex-shrink-0">{log.file}</span>
                  <button
                    onClick={() => copyToClipboard(`${log.timestamp} ${log.level.toUpperCase()}: ${log.message} (${log.file})`)}
                    className="text-gray-400 hover:text-white"
                  >
                    <Copy className="h-4 w-4" />
                  </button>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>

      {/* AJAX Calls */}
      <div className="mb-6">
        <button
          onClick={() => toggleSection('ajax')}
          className="flex items-center space-x-2 text-lg font-semibold text-gray-900 mb-3 hover:text-blue-600"
        >
          {expandedSections.ajax ? <ChevronDown className="h-5 w-5" /> : <ChevronRight className="h-5 w-5" />}
          <Zap className="h-5 w-5" />
          <span>AJAX Calls ({bugData.stackTrace.ajaxCalls.length})</span>
        </button>
        {expandedSections.ajax && (
          <div className="space-y-4">
            {bugData.stackTrace.ajaxCalls.map((call, index) => (
              <div key={index} className="border border-gray-200 rounded-lg p-4">
                <div className="flex items-center justify-between mb-3">
                  <div className="flex items-center space-x-3">
                    <span className={`px-2 py-1 rounded text-xs font-medium ${
                      call.method === 'GET' ? 'bg-blue-100 text-blue-800' : 
                      call.method === 'POST' ? 'bg-green-100 text-green-800' :
                      'bg-gray-100 text-gray-800'
                    }`}>
                      {call.method}
                    </span>
                    <span className="font-mono text-sm text-gray-900">{call.url}</span>
                  </div>
                  <div className="flex items-center space-x-2 text-sm text-gray-500">
                    <span className={`px-2 py-1 rounded text-xs font-medium ${
                      call.status >= 200 && call.status < 300 ? 'bg-green-100 text-green-800' :
                      call.status >= 400 ? 'bg-red-100 text-red-800' :
                      'bg-yellow-100 text-yellow-800'
                    }`}>
                      {call.status}
                    </span>
                    <span>{call.responseTime}</span>
                    <span>{call.timestamp}</span>
                  </div>
                </div>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                  <div>
                    <h5 className="font-medium text-gray-900 mb-2">Request</h5>
                    <div className="bg-gray-50 rounded p-3 font-mono">
                      <pre className="text-xs text-gray-600">
                        {JSON.stringify(call.requestHeaders, null, 2)}
                      </pre>
                    </div>
                  </div>
                  
                  <div>
                    <h5 className="font-medium text-gray-900 mb-2">Response</h5>
                    <div className="bg-gray-50 rounded p-3 font-mono">
                      <pre className="text-xs text-gray-600">
                        {JSON.stringify(call.responseBody, null, 2)}
                      </pre>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* URL Navigation */}
      <div className="mb-6">
        <button
          onClick={() => toggleSection('navigation')}
          className="flex items-center space-x-2 text-lg font-semibold text-gray-900 mb-3 hover:text-blue-600"
        >
          {expandedSections.navigation ? <ChevronDown className="h-5 w-5" /> : <ChevronRight className="h-5 w-5" />}
          <Globe className="h-5 w-5" />
          <span>URL Navigation ({bugData.stackTrace.navigation.length})</span>
        </button>
        {expandedSections.navigation && (
          <div className="space-y-3">
            {bugData.stackTrace.navigation.map((nav, index) => (
              <div key={index} className="flex items-center space-x-4 p-3 bg-gray-50 rounded-lg">
                <span className="text-gray-500 font-mono text-sm min-w-0 flex-shrink-0">{nav.timestamp}</span>
                <span className={`px-2 py-1 rounded text-xs font-medium min-w-0 flex-shrink-0 ${
                  nav.action === 'navigate' ? 'bg-blue-100 text-blue-800' :
                  nav.action === 'form_submit' ? 'bg-green-100 text-green-800' :
                  nav.action === 'error_redirect' ? 'bg-red-100 text-red-800' :
                  'bg-gray-100 text-gray-800'
                }`}>
                  {nav.action.replace('_', ' ').toUpperCase()}
                </span>
                <span className="font-mono text-sm text-gray-900 flex-1">{nav.url}</span>
                {nav.referrer && (
                  <span className="text-gray-500 text-xs">from: {nav.referrer}</span>
                )}
                {nav.data && (
                  <span className="text-gray-500 text-xs">data: {nav.data}</span>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};

export default BugTracker;
