import { createStore, applyMiddleware, compose } from 'redux'
import reducers, { initialState } from './reducers'

const middlewares = []

const composeEnhancers = (typeof window !== 'undefined' &&
  window.__REDUX_DEVTOOLS_EXTENSION_COMPOSE__) || compose

export default createStore(
  reducers,
  {
    ...initialState,
  },
  composeEnhancers(
    applyMiddleware(...middlewares)
  )
)