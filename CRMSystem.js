# WEBERIS CRM - Komplette filer

Her er alle filene du trenger for å pushe til GitHub. For hver fil, kopier innholdet og last opp til GitHub.

## 1. src/CRMSystem.js

```jsx
import React, { useState } from 'react';

const CRMSystem = () => {
  const [activeTab, setActiveTab] = useState('dashboard');
  const [customers, setCustomers] = useState([
    { id: 1, name: 'Nordisk Teknologi AS', contact: 'Erik Hansen', email: 'erik@nordisktek.no', phone: '91234567', status: 'Aktiv' },
    { id: 2, name: 'Vestlandet Konsult', contact: 'Maria Olsen', email: 'maria@vestlandetkonsult.no', phone: '92345678', status: 'Prospekt' },
    { id: 3, name: 'Oslo Digital', contact: 'Anders Berg', email: 'anders@oslodigital.no', phone: '93456789', status: 'Aktiv' }
  ]);
  const [newCustomer, setNewCustomer] = useState({ name: '', contact: '', email: '', phone: '', status: 'Prospekt' });
  const [searchTerm, setSearchTerm] = useState('');

  const filteredCustomers = customers.filter(customer => 
    customer.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    customer.contact.toLowerCase().includes(searchTerm.toLowerCase())
  );

  const addCustomer = () => {
    if (newCustomer.name && newCustomer.contact) {
      setCustomers([...customers, { ...newCustomer, id: customers.length + 1 }]);
      setNewCustomer({ name: '', contact: '', email: '', phone: '', status: 'Prospekt' });
    }
  };

  const deleteCustomer = (id) => {
    setCustomers(customers.filter(customer => customer.id !== id));
  };

  return (
    <div className="flex flex-col min-h-screen bg-gray-100">
      {/* Header */}
      <header className="bg-blue-600 text-white p-4">
        <h1 className="text-2xl font-bold">WEBERIS CRM</h1>
      </header>
      
      {/* Navigation */}
      <nav className="bg-blue-800 text-white p-2">
        <div className="flex space-x-4">
          <button 
            className={`px-3 py-1 rounded ${activeTab === 'dashboard' ? 'bg-blue-500' : 'hover:bg-blue-700'}`}
            onClick={() => setActiveTab('dashboard')}
          >
            Dashboard
          </button>
          <button 
            className={`px-3 py-1 rounded ${activeTab === 'customers' ? 'bg-blue-500' : 'hover:bg-blue-700'}`}
            onClick={() => setActiveTab('customers')}
          >
            Kunder
          </button>
          <button 
            className={`px-3 py-1 rounded ${activeTab === 'tasks' ? 'bg-blue-500' : 'hover:bg-blue-700'}`}
            onClick={() => setActiveTab('tasks')}
          >
            Oppgaver
          </button>
          <button 
            className={`px-3 py-1 rounded ${activeTab === 'reports' ? 'bg-blue-500' : 'hover:bg-blue-700'}`}
            onClick={() => setActiveTab('reports')}
          >
            Rapporter
          </button>
        </div>
      </nav>
      
      {/* Main Content */}
      <main className="flex-grow p-4">
        {activeTab === 'dashboard' && (
          <div>
            <h2 className="text-xl font-semibold mb-4">Dashboard</h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="bg-white p-4 rounded shadow">
                <h3 className="font-medium text-gray-700">Totalt Antall Kunder</h3>
                <p className="text-2xl font-bold text-blue-600">{customers.length}</p>
              </div>
              <div className="bg-white p-4 rounded shadow">
                <h3 className="font-medium text-gray-700">Aktive Kunder</h3>
                <p className="text-2xl font-bold text-green-600">
                  {customers.filter(c => c.status === 'Aktiv').length}
                </p>
              </div>
              <div className="bg-white p-4 rounded shadow">
                <h3 className="font-medium text-gray-700">Prospekter</h3>
                <p className="text-2xl font-bold text-yellow-600">
                  {customers.filter(c => c.status === 'Prospekt').length}
                </p>
              </div>
            </div>
          </div>
        )}
        
        {activeTab === 'customers' && (
          <div>
            <h2 className="text-xl font-semibold mb-4">Kundeoversikt</h2>
            
            {/* Search and Add */}
            <div className="flex flex-col md:flex-row mb-4 gap-4">
              <div className="md:w-1/2">
                <input
                  type="text"
                  placeholder="Søk etter kunde..."
                  className="w-full p-2 border rounded"
                  value={searchTerm}
                  onChange={e => setSearchTerm(e.target.value)}
                />
              </div>
              <div className="md:w-1/2 flex justify-end">
                <button 
                  className="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded"
                  onClick={() => document.getElementById('addCustomerForm').classList.toggle('hidden')}
                >
                  Legg til ny kunde
                </button>
              </div>
            </div>
            
            {/* Add Customer Form */}
            <div id="addCustomerForm" className="mb-4 p-4 bg-white rounded shadow hidden">
              <h3 className="font-medium mb-2">Legg til ny kunde</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <input
                  type="text"
                  placeholder="Bedriftsnavn"
                  className="p-2 border rounded"
                  value={newCustomer.name}
                  onChange={e => setNewCustomer({...newCustomer, name: e.target.value})}
                />
                <input
                  type="text"
                  placeholder="Kontaktperson"
                  className="p-2 border rounded"
                  value={newCustomer.contact}
                  onChange={e => setNewCustomer({...newCustomer, contact: e.target.value})}
                />
                <input
                  type="email"
                  placeholder="E-post"
                  className="p-2 border rounded"
                  value={newCustomer.email}
                  onChange={e => setNewCustomer({...newCustomer, email: e.target.value})}
                />
                <input
                  type="tel"
                  placeholder="Telefon"
                  className="p-2 border rounded"
                  value={newCustomer.phone}
                  onChange={e => setNewCustomer({...newCustomer, phone: e.target.value})}
                />
                <select 
                  className="p-2 border rounded"
                  value={newCustomer.status}
                  onChange={e => setNewCustomer({...newCustomer, status: e.target.value})}
                >
                  <option value="Prospekt">Prospekt</option>
                  <option value="Aktiv">Aktiv kunde</option>
                  <option value="Inaktiv">Inaktiv</option>
                </select>
              </div>
              <div className="flex justify-end">
                <button 
                  className="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded"
                  onClick={addCustomer}
                >
                  Lagre kunde
                </button>
              </div>
            </div>
            
            {/* Customer Table */}
            <div className="bg-white rounded shadow overflow-x-auto">
              <table className="min-w-full">
                <thead className="bg-gray-100">
                  <tr>
                    <th className="py-2 px-4 text-left">Bedrift</th>
                    <th className="py-2 px-4 text-left">Kontaktperson</th>
                    <th className="py-2 px-4 text-left">E-post</th>
                    <th className="py-2 px-4 text-left">Telefon</th>
                    <th className="py-2 px-4 text-left">Status</th>
                    <th className="py-2 px-4 text-left">Handling</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredCustomers.map(customer => (
                    <tr key={customer.id} className="border-t">
                      <td className="py-2 px-4">{customer.name}</td>
                      <td className="py-2 px-4">{customer.contact}</td>
                      <td className="py-2 px-4">{customer.email}</td>
                      <td className="py-2 px-4">{customer.phone}</td>
                      <td className="py-2 px-4">
                        <span className={`px-2 py-1 rounded text-xs ${
                          customer.status === 'Aktiv' ? 'bg-green-100 text-green-800' : 
                          customer.status === 'Prospekt' ? 'bg-yellow-100 text-yellow-800' : 
                          'bg-gray-100 text-gray-800'
                        }`}>
                          {customer.status}
                        </span>
                      </td>
                      <td className="py-2 px-4">
                        <button 
                          className="text-red-500 hover:text-red-700"
                          onClick={() => deleteCustomer(customer.id)}
                        >
                          Slett
                        </button>
                      </td>
                    </tr>
                  ))}
                  {filteredCustomers.length === 0 && (
                    <tr>
                      <td colSpan="6" className="py-4 text-center text-gray-500">
                        Ingen kunder funnet
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}
        
        {activeTab === 'tasks' && (
          <div>
            <h2 className="text-xl font-semibold mb-4">Oppgaver</h2>
            <p className="text-gray-600">Oppgavemodulen er under utvikling...</p>
          </div>
        )}
        
        {activeTab === 'reports' && (
          <div>
            <h2 className="text-xl font-semibold mb-4">Rapporter</h2>
            <p className="text-gray-600">Rapportmodulen er under utvikling...</p>
          </div>
        )}
      </main>
      
      {/* Footer */}
      <footer className="bg-gray-200 p-4 text-center text-gray-600 text-sm">
        WEBERIS CRM © 2025 - Et enkelt og effektivt CRM-system
      </footer>
    </div>
  );
};

export default CRMSystem;
```

## 2. src/App.js

```jsx
import React from 'react';
import CRMSystem from './CRMSystem';

function App() {
  return (
    <div className="App">
      <CRMSystem />
    </div>
  );
}

export default App;
```

## 3. src/index.css

```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

## 4. tailwind.config.js

```js
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./src/**/*.{js,jsx,ts,tsx}",
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
```

## 5. package.json

```json
{
  "name": "weberis-crm",
  "version": "0.1.0",
  "private": true,
  "dependencies": {
    "react": "^18.2.0",
    "react-dom": "^18.2.0",
    "react-scripts": "5.0.1",
    "web-vitals": "^2.1.4"
  },
  "devDependencies": {
    "autoprefixer": "^10.4.14",
    "postcss": "^8.4.27",
    "tailwindcss": "^3.3.3"
  },
  "scripts": {
    "start": "react-scripts start",
    "build": "react-scripts build",
    "test": "react-scripts test",
    "eject": "react-scripts eject"
  },
  "eslintConfig": {
    "extends": [
      "react-app",
      "react-app/jest"
    ]
  },
  "browserslist": {
    "production": [
      ">0.2%",
      "not dead",
      "not op_mini all"
    ],
    "development": [
      "last 1 chrome version",
      "last 1 firefox version",
      "last 1 safari version"
    ]
  }
}
```

## 6. README.md

```md
# WEBERIS CRM

En enkel og effektiv CRM-løsning for bedrifter.

## Funksjoner

- Dashboard med nøkkeltall
- Kundeoversikt med søkefunksjonalitet
- Mulighet for å legge til og slette kunder
- Moduler for oppgavehåndtering og rapporter (under utvikling)

## Teknologier

- React
- Tailwind CSS

## Installasjon

1. Klone repoet
2. Kjør `npm install`
3. Kjør `npm start`

## Lisens

Dette prosjektet er lisensiert under MIT-lisensen.
```
