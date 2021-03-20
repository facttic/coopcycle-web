import _ from 'lodash'
import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  UPDATE_TASK,
  DELETE_GROUP_SUCCESS,
  REMOVE_TASK,
} from "./actions";
import { taskUtils as utils } from '../../coopcycle-frontend-js/logistics/redux'

const initialState = {
  byId: {}
}

export default (state = initialState, action) => {
  switch (action.type) {
    case MODIFY_TASK_LIST_REQUEST: {
      let newItems = utils.addOrReplaceTasks(state.byId, action.tasks)

      return {
        ...state,
        byId: newItems,
      }
    }
    case MODIFY_TASK_LIST_REQUEST_SUCCESS: {
      let newItems = utils.addOrReplaceTasks(state.byId, action.taskList.items)

      return {
        ...state,
        byId: newItems,
      }
    }
    case UPDATE_TASK: {
      let newItems = Object.assign({}, state.byId)
      let task = action.task

      if (Object.prototype.hasOwnProperty.call(state.byId, task['@id'])) {
        // copy object to keep 'position' property
        newItems[task['@id']] = Object.assign({}, state.byId[task['@id']], task)
      } else {
        newItems[task['@id']] = task
      }

      return {
        ...state,
        byId: newItems,
      }
    }
    case DELETE_GROUP_SUCCESS:

      const tasksMatchingGroup = _.filter(state.byId, t => t.group && t.group['@id'] === action.group)

      if (tasksMatchingGroup.length === 0) {
        return state
      }

      return {
        ...state,
        byId: _.omit(state.byId, tasksMatchingGroup.map(t => t['@id'])),
      }
    case REMOVE_TASK:
      let task = action.task

      if (!Object.prototype.hasOwnProperty.call(state.byId, task['@id'])) {
        return state
      }

      return {
        ...state,
        byId: _.pickBy(state.byId, (value, key) => key !== task['@id']),
      }
    default:
      return state
  }
}
