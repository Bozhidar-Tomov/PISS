package bg.sofia.uni.fmi.mjt.crowdpulse.service;

import bg.sofia.uni.fmi.mjt.crowdpulse.model.Command;
import bg.sofia.uni.fmi.mjt.crowdpulse.repository.CommandRepository;
import com.google.gson.Gson;

import java.util.Map;

public class CommandService {
    private final CommandRepository commandRepository;
    private final Gson gson = new Gson();

    public CommandService() {
        this.commandRepository = new CommandRepository();
    }

    public Command getActiveCommand() {
        return commandRepository.findActiveCommand();
    }

    public void createCommand(String type, Map<String, Object> data) {
        // Deactivate previous
        commandRepository.deactivateAll();

        String jsonData = gson.toJson(data);
        Command cmd = new Command(null, type, jsonData, true);
        commandRepository.save(cmd);
    }

    public void deactivateCommand() {
        commandRepository.deactivateAll();
    }
}
