import React from 'react';
import { Link } from 'react-router-dom';
import { Bug } from 'lucide-react';

const Home = () => {
  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center">
      <div className="max-w-md mx-auto text-center">
        <Bug className="h-16 w-16 text-red-500 mx-auto mb-4" />
        <h1 className="text-3xl font-bold text-gray-900 mb-4">Bug Tracker</h1>
        <p className="text-gray-600 mb-8">Track and manage your application bugs</p>
        <Link 
          to="/bug" 
          className="inline-block bg-red-500 text-white px-6 py-3 rounded-lg hover:bg-red-600 transition-colors"
        >
          View Bug Report
        </Link>
      </div>
    </div>
  );
};

export default Home;
